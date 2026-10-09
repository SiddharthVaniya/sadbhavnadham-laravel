<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Match marketers inside Meta ad / campaign strings — anywhere in the text,
 * not only the first pipe segment (e.g. "Theme | Ashvini | Cause").
 */
class MarketerNameMatcher
{
    public const MATCHED_VIA_AD_NAME_PREFIX = 'ad_name_prefix';

    public const MATCHED_VIA_NAME_IN_TEXT = 'name_in_text';

    public const MATCHED_VIA_REFERRAL_CODE = 'referral_code';

    public const MATCHED_VIA_UNMATCHED = 'unmatched';

    /** @var Collection<int, User>|null */
    private static ?Collection $marketerCache = null;

    public static function clearMarketerCache(): void
    {
        self::$marketerCache = null;
    }

    /**
     * @return Collection<int, User>
     */
    private static function marketersWithReferralCode(): Collection
    {
        if (self::$marketerCache !== null) {
            return self::$marketerCache;
        }

        self::$marketerCache = User::query()
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->get(['id', 'name', 'referral_code']);

        return self::$marketerCache;
    }

    /**
     * @return array{
     *     marketer: ?string,
     *     date: ?string,
     *     brand: ?string,
     *     theme: ?string,
     *     cause: ?string,
     *     segments: list<string>
     * }
     */
    public static function parsePipeSegments(?string $name): array
    {
        $raw = trim((string) $name);
        $segments = $raw === ''
            ? []
            : array_values(array_filter(array_map('trim', explode('|', $raw)), fn (string $s) => $s !== ''));

        return [
            'marketer' => $segments[0] ?? null,
            'date' => $segments[1] ?? null,
            'brand' => $segments[2] ?? null,
            'theme' => $segments[3] ?? null,
            'cause' => $segments[4] ?? null,
            'segments' => $segments,
        ];
    }

    public static function prefixFromName(?string $name): ?string
    {
        return StaffReferral::metaAdNamePrefix($name);
    }

    public static function partnerFromAdName(?string $name): ?User
    {
        return self::partnerFromMetaStrings($name, null, null);
    }

    /**
     * Combined searchable text from ad / campaign / ad set (lowercase).
     */
    public static function normalizeSearchBlob(
        ?string $adName,
        ?string $campaignName = null,
        ?string $adsetName = null,
    ): string {
        $parts = array_filter([
            trim((string) $adName),
            trim((string) $campaignName),
            trim((string) $adsetName),
        ], fn (string $part) => $part !== '');

        return mb_strtolower(implode(' | ', $parts));
    }

    /**
     * Resolve marketer from full Meta strings (name or referral code anywhere in text).
     */
    public static function partnerFromMetaStrings(
        ?string $adName,
        ?string $campaignName = null,
        ?string $adsetName = null,
    ): ?User {
        $haystack = self::normalizeSearchBlob($adName, $campaignName, $adsetName);

        if ($haystack === '') {
            return null;
        }

        $prefixPartner = StaffReferral::partnerFromMetaAdNamePrefix($adName);
        if ($prefixPartner) {
            return $prefixPartner;
        }

        $winner = self::bestPartnerMatch($haystack);

        return $winner['user'] ?? null;
    }

    /**
     * @return array{user_id: ?int, matched_via: string}
     */
    public static function resolveAttribution(
        ?string $adName,
        ?string $campaignName = null,
        ?string $adsetName = null,
    ): array {
        $haystack = self::normalizeSearchBlob($adName, $campaignName, $adsetName);

        if ($haystack === '') {
            return [
                'user_id' => null,
                'matched_via' => self::MATCHED_VIA_UNMATCHED,
            ];
        }

        $prefixPartner = StaffReferral::partnerFromMetaAdNamePrefix($adName);
        if ($prefixPartner) {
            return [
                'user_id' => $prefixPartner->id,
                'matched_via' => self::MATCHED_VIA_AD_NAME_PREFIX,
            ];
        }

        $winner = self::bestPartnerMatch($haystack);

        if ($winner['user'] === null) {
            return [
                'user_id' => null,
                'matched_via' => self::MATCHED_VIA_UNMATCHED,
            ];
        }

        return [
            'user_id' => $winner['user']->id,
            'matched_via' => $winner['via'],
        ];
    }

    /**
     * @return array{user: ?User, via: string, score: int}
     */
    private static function bestPartnerMatch(string $haystack): array
    {
        /** @var array<int, array{user: User, score: int, via: string}> $byUserId */
        $byUserId = [];

        foreach (self::marketersWithReferralCode() as $user) {
            foreach (self::scorePartnerInHaystack($user, $haystack) as $hit) {
                $existing = $byUserId[$user->id] ?? null;

                if ($existing === null || $hit['score'] > $existing['score']) {
                    $byUserId[$user->id] = [
                        'user' => $user,
                        'score' => $hit['score'],
                        'via' => $hit['via'],
                    ];
                }
            }
        }

        if ($byUserId === []) {
            return ['user' => null, 'via' => self::MATCHED_VIA_UNMATCHED, 'score' => 0];
        }

        $sorted = collect($byUserId)->sortByDesc('score')->values();
        $top = $sorted->first();
        $second = $sorted->get(1);

        if ($second !== null && $second['score'] === $top['score']) {
            return ['user' => null, 'via' => self::MATCHED_VIA_UNMATCHED, 'score' => 0];
        }

        return [
            'user' => $top['user'],
            'via' => $top['via'],
            'score' => $top['score'],
        ];
    }

    /**
     * @return list<array{score: int, via: string}>
     */
    private static function scorePartnerInHaystack(User $user, string $haystack): array
    {
        $hits = [];
        $name = trim((string) $user->name);
        $code = strtolower(trim((string) $user->referral_code));
        $firstName = $name !== '' ? trim(explode(' ', $name, 2)[0]) : '';

        if ($name !== '' && mb_strlen($name) >= 2) {
            $nameLower = mb_strtolower($name);

            if (mb_strpos($haystack, $nameLower) !== false) {
                $hits[] = [
                    'score' => 200 + mb_strlen($name),
                    'via' => self::MATCHED_VIA_NAME_IN_TEXT,
                ];
            }
        }

        if ($firstName !== '' && mb_strlen($firstName) >= 3 && self::containsWholeWord($haystack, $firstName)) {
            $hits[] = [
                'score' => 120 + mb_strlen($firstName),
                'via' => self::MATCHED_VIA_NAME_IN_TEXT,
            ];
        }

        if ($code !== '' && mb_strlen($code) >= 3 && self::containsWholeWord($haystack, $code)) {
            $hits[] = [
                'score' => 100 + mb_strlen($code),
                'via' => self::MATCHED_VIA_REFERRAL_CODE,
            ];
        }

        return $hits;
    }

    private static function containsWholeWord(string $haystack, string $word): bool
    {
        $word = mb_strtolower(trim($word));

        if ($word === '' || mb_strlen($word) < 2) {
            return false;
        }

        $escaped = preg_quote($word, '/');

        return preg_match('/(?<![a-z0-9])'.$escaped.'(?![a-z0-9])/iu', $haystack) === 1;
    }

    /**
     * Apply free-text search across campaign / ad set / ad names.
     *
     * @param  Builder<\App\Models\MetaAdSpendDaily>  $query
     */
    public static function applySmartSearch(Builder $query, string $q): void
    {
        $q = trim($q);

        if ($q === '') {
            return;
        }

        $like = '%'.addcslashes($q, '%_\\').'%';

        $query->where(function (Builder $builder) use ($like): void {
            $builder->where('campaign_name', 'like', $like)
                ->orWhere('adset_name', 'like', $like)
                ->orWhere('ad_name', 'like', $like);
        });
    }

    /**
     * Filter rows for a marketer: stored user_id OR name/code appears anywhere in Meta strings.
     *
     * @param  Builder<\App\Models\MetaAdSpendDaily>  $query
     */
    public static function applyMarketerFilter(Builder $query, int|string|null $userId): void
    {
        if ($userId === null || $userId === '') {
            return;
        }

        $user = User::query()->find((int) $userId);

        if (! $user) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function (Builder $builder) use ($user): void {
            $builder->where('user_id', $user->id)
                ->orWhere(function (Builder $inner) use ($user): void {
                    self::applyPartnerTextMatch($inner, $user);
                });
        });
    }

    /**
     * @param  Builder<\App\Models\MetaAdSpendDaily>  $query
     */
    public static function applyPartnerTextMatch(Builder $query, User $partner): void
    {
        $name = trim((string) $partner->name);
        $firstName = $name !== '' ? trim(explode(' ', $name, 2)[0]) : '';
        $code = trim((string) $partner->referral_code);

        $terms = array_values(array_filter(array_unique([
            $name,
            $firstName,
            $code,
        ]), fn (string $term) => $term !== '' && mb_strlen($term) >= 2));

        if ($terms === []) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->where(function (Builder $builder) use ($terms): void {
            foreach ($terms as $term) {
                $like = '%'.addcslashes($term, '%_\\').'%';
                $builder->orWhere(function (Builder $columns) use ($like): void {
                    $columns->where('ad_name', 'like', $like)
                        ->orWhere('campaign_name', 'like', $like)
                        ->orWhere('adset_name', 'like', $like);
                });
            }
        });
    }

    /**
     * Re-resolve user_id / matched_via on existing snapshot rows (after matcher improvements).
     *
     * @return int Rows updated
     */
    public static function reattributeSnapshotRows(?string $fromDate = null, ?string $toDate = null): int
    {
        $query = \App\Models\MetaAdSpendDaily::query();

        if ($fromDate !== null) {
            $query->whereDate('spend_date', '>=', $fromDate);
        }

        if ($toDate !== null) {
            $query->whereDate('spend_date', '<=', $toDate);
        }

        $updated = 0;

        $query->orderBy('id')->chunkById(500, function (Collection $rows) use (&$updated): void {
            foreach ($rows as $row) {
                $attribution = self::resolveAttribution(
                    $row->ad_name,
                    $row->campaign_name,
                    $row->adset_name,
                );

                if ((int) $row->user_id !== (int) ($attribution['user_id'] ?? 0)
                    || $row->matched_via !== $attribution['matched_via']) {
                    $row->forceFill([
                        'user_id' => $attribution['user_id'],
                        'matched_via' => $attribution['matched_via'],
                    ])->save();
                    $updated++;
                }
            }
        });

        return $updated;
    }

    /**
     * @return Collection<int, array{id: int, name: string, code: string}>
     */
    public static function marketerOptions(): Collection
    {
        return User::query()
            ->whereNotNull('referral_code')
            ->where('referral_code', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'referral_code'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'code' => (string) $user->referral_code,
            ]);
    }
}

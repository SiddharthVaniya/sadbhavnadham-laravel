<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Match marketers inside Meta ad / campaign pipe-separated names
 * (e.g. "Ashvini | 09/10 | Sadbhavna | Pitru Amas | Old age").
 */
class MarketerNameMatcher
{
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
        return StaffReferral::partnerFromMetaAdNamePrefix($name);
    }

    /**
     * @return array{user_id: ?int, matched_via: string}
     */
    public static function resolveAttribution(?string $adName): array
    {
        $partner = self::partnerFromAdName($adName);

        if ($partner) {
            return [
                'user_id' => $partner->id,
                'matched_via' => 'ad_name_prefix',
            ];
        }

        return [
            'user_id' => null,
            'matched_via' => 'unmatched',
        ];
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
     * Filter rows for a marketer by resolved user_id or "{FirstName} |" prefix on ad_name.
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
                    StaffReferral::whereUtmContentMatchesPartnerAd($inner, $user, 'ad_name');
                });
        });
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

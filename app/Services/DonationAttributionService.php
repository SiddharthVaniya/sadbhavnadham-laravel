<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Support\AdminAnalyticsData;
use App\Support\Attribution\AttributionNormalizer;
use App\Support\Attribution\AttributionParameters;
use App\Support\Attribution\AttributionTaxonomy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class DonationAttributionService
{
    public const CHANNEL_WEB = 'web';

    public const CHANNEL_CAMPAIGN = 'campaign';

    public const CHANNEL_RAZORPAY_QR = 'razorpay_qr';

    public const CHANNEL_OFFLINE = 'offline';

    public const CHANNEL_PAYMENT_LINK = 'payment_link';

    public const CHANNEL_IMPORT = 'import';

    public const CHANNEL_DANAMOJO = 'danamojo';

    public const CHANNEL_WORDPRESS = 'wordpress';

    public const CHANNEL_UNKNOWN = 'unknown';

    /**
     * @return array<string, string>
     */
    public static function channelLabels(): array
    {
        return [
            self::CHANNEL_WEB => 'Web checkout',
            self::CHANNEL_CAMPAIGN => 'Campaign page',
            self::CHANNEL_RAZORPAY_QR => 'Razorpay QR',
            self::CHANNEL_OFFLINE => 'Offline / manual',
            self::CHANNEL_PAYMENT_LINK => 'Payment link',
            self::CHANNEL_IMPORT => 'Imported',
            self::CHANNEL_DANAMOJO => 'Danamojo sync',
            self::CHANNEL_WORDPRESS => 'WordPress site',
            self::CHANNEL_UNKNOWN => 'Unknown',
        ];
    }

    public static function channelLabel(?string $channel): string
    {
        if ($channel === null || $channel === '') {
            return self::channelLabels()[self::CHANNEL_UNKNOWN];
        }

        return self::channelLabels()[$channel] ?? Str::headline($channel);
    }

    /**
     * Marketing traffic sources for admin filters (normalized taxonomy).
     *
     * @return array<string, string>
     */
    public static function trafficSourceOptions(): array
    {
        return AttributionTaxonomy::sourceOptions();
    }

    /**
     * Meta sub-platforms (Facebook, Instagram, etc.).
     *
     * @return array<string, string>
     */
    public static function platformOptions(): array
    {
        return AttributionTaxonomy::platformOptions();
    }

    public static function trafficSourceLabel(DonationOrder|DonationSubscription|null $record): string
    {
        if ($record === null) {
            return 'Unknown';
        }

        if (filled($record->attr_source)) {
            return AttributionTaxonomy::displayLabel(
                $record->attr_source,
                $record->attr_medium,
                $record->attr_platform,
            );
        }

        $normalized = AttributionNormalizer::normalize([
            'utm_source' => $record->utm_source,
            'utm_medium' => $record->utm_medium,
            'utm_campaign' => $record->utm_campaign,
            'utm_content' => $record->utm_content,
            'utm_term' => $record->utm_term,
            'referrer' => $record->referrer,
            'landing_path' => $record->landing_path,
        ]);

        if (filled($normalized['attr_source'])) {
            return AttributionTaxonomy::displayLabel(
                $normalized['attr_source'],
                $normalized['attr_medium'],
                $normalized['attr_platform'],
            );
        }

        $bucket = self::resolveTrafficSourceBucket(
            $record->utm_source,
            $record->utm_medium,
            $record->referrer,
            $record->landing_path,
        );

        return match ($bucket) {
            'facebook' => 'Meta · Facebook',
            'instagram' => 'Meta · Instagram',
            'whatsapp' => 'WhatsApp',
            'google' => 'Google',
            'youtube' => 'YouTube',
            'organic' => 'Direct',
            default => filled($record->utm_source)
                ? Str::headline((string) $record->utm_source)
                : 'Unknown',
        };
    }

    public static function normalizeOrderAttributes(DonationOrder $order): bool
    {
        $normalized = AttributionNormalizer::normalizedPayload([
            'utm_source' => $order->utm_source,
            'utm_medium' => $order->utm_medium,
            'utm_campaign' => $order->utm_campaign,
            'utm_content' => $order->utm_content,
            'utm_term' => $order->utm_term,
            'referrer' => $order->referrer,
            'landing_path' => $order->landing_path,
        ]);

        if ($normalized === []) {
            return false;
        }

        $before = $order->only(array_keys($normalized));
        $order->forceFill($normalized)->save();

        return $before !== $normalized;
    }

    public static function resolveTrafficSourceBucket(
        ?string $utmSource,
        ?string $utmMedium,
        ?string $referrer,
        ?string $landingPath = null,
    ): string {
        $source = mb_strtolower(trim((string) $utmSource));
        $medium = mb_strtolower(trim((string) $utmMedium));
        $ref = mb_strtolower(trim((string) $referrer));
        $path = mb_strtolower(trim((string) $landingPath));
        $haystack = trim($source.' '.$medium.' '.$ref.' '.$path);

        if ($haystack !== '' && (
            str_contains($haystack, 'instagram')
            || str_contains($haystack, 'igshid')
            || str_contains($haystack, 'ig_')
            || preg_match('/\big\b/', $haystack)
            || str_contains($medium, 'instagram_reels')
            || str_contains($medium, 'ig')
        )) {
            return 'instagram';
        }

        if ($haystack !== '' && (
            str_contains($haystack, 'facebook')
            || str_contains($haystack, 'fbclid')
            || str_contains($haystack, 'fb.com')
            || str_contains($haystack, 'fb.')
            || preg_match('/\bfb\b/', $haystack)
            || $source === 'meta'
            || str_contains($source, 'meta')
        )) {
            return 'facebook';
        }

        if ($haystack !== '' && (
            str_contains($haystack, 'whatsapp')
            || str_contains($haystack, 'wa.me')
            || str_contains($haystack, 'wa.aisensy')
            || str_contains($haystack, 'aisensy')
        )) {
            return 'whatsapp';
        }

        if ($haystack !== '' && (
            str_contains($haystack, 'youtube')
            || str_contains($haystack, 'youtu.be')
        )) {
            return 'youtube';
        }

        if ($haystack !== '' && (
            str_contains($haystack, 'google')
            || str_contains($haystack, 'gclid')
            || str_contains($haystack, 'wbraid')
            || str_contains($haystack, 'gbraid')
            || (str_contains($medium, 'cpc') && str_contains($source, 'google'))
        )) {
            return 'google';
        }

        if ($source === '' && $medium === '') {
            return 'organic';
        }

        if (in_array($source, ['organic', 'direct', '(direct)', 'none'], true)
            || str_contains($medium, 'organic')) {
            return 'organic';
        }

        return 'unknown';
    }

    public static function applyTrafficSourceFilter(Builder $query, string $source): void
    {
        $source = mb_strtolower(trim($source));

        if ($source === '' || (
            ! array_key_exists($source, self::trafficSourceOptions())
            && ! in_array($source, ['facebook', 'instagram'], true)
        )) {
            return;
        }

        // Legacy filter keys from older admin URLs.
        if ($source === 'facebook') {
            self::applyNormalizedSourceFilter($query, AttributionTaxonomy::SOURCE_META);

            return;
        }

        if ($source === 'instagram') {
            self::applyNormalizedSourceFilter($query, AttributionTaxonomy::SOURCE_META);
            self::applyPlatformFilter($query, AttributionTaxonomy::PLATFORM_INSTAGRAM);

            return;
        }

        if ($source === AttributionTaxonomy::SOURCE_ORGANIC) {
            $query->where(function (Builder $organic) {
                $organic->where('attr_source', AttributionTaxonomy::SOURCE_ORGANIC)
                    ->orWhere(function (Builder $legacy) {
                        self::applyLegacyOrganicFilter($legacy);
                    });
            });

            return;
        }

        if ($source === AttributionTaxonomy::SOURCE_UNKNOWN) {
            $query->where(function (Builder $unknown) {
                $unknown->where('attr_source', AttributionTaxonomy::SOURCE_UNKNOWN)
                    ->orWhere(function (Builder $legacy) {
                        foreach (array_keys(self::trafficSourceOptions()) as $known) {
                            if (in_array($known, [AttributionTaxonomy::SOURCE_UNKNOWN, AttributionTaxonomy::SOURCE_ORGANIC], true)) {
                                continue;
                            }

                            $legacy->whereNot(function (Builder $inner) use ($known) {
                                if ($known === 'facebook') {
                                    self::applyNormalizedSourceFilter($inner, AttributionTaxonomy::SOURCE_META);
                                } elseif ($known === 'instagram') {
                                    self::applyNormalizedSourceFilter($inner, AttributionTaxonomy::SOURCE_META);
                                    self::applyPlatformFilter($inner, AttributionTaxonomy::PLATFORM_INSTAGRAM);
                                } else {
                                    self::applyNormalizedSourceFilter($inner, $known);
                                }
                            });
                        }
                    });
            });

            return;
        }

        self::applyNormalizedSourceFilter($query, $source);
    }

    public static function applyPlatformFilter(Builder $query, string $platform): void
    {
        $platform = mb_strtolower(trim($platform));

        if ($platform === '' || ! array_key_exists($platform, self::platformOptions())) {
            return;
        }

        $query->where(function (Builder $group) use ($platform) {
            $group->where('attr_platform', $platform)
                ->orWhere(function (Builder $legacy) use ($platform) {
                    $legacy->whereNull('attr_platform');
                    self::applyLegacyPlatformFilter($legacy, $platform);
                });
        });
    }

    public static function applyNormalizedSourceFilter(Builder $query, string $source): void
    {
        $query->where(function (Builder $group) use ($source) {
            $group->where('attr_source', $source)
                ->orWhere(function (Builder $legacy) use ($source) {
                    $legacy->whereNull('attr_source');
                    self::applyLegacySourceFilter($legacy, $source);
                });
        });
    }

    private static function applyLegacySourceFilter(Builder $query, string $source): void
    {
        if ($source === AttributionTaxonomy::SOURCE_META) {
            $query->where(function (Builder $group) {
                foreach (['%facebook%', '%fb.com%', '%fb.%', '%meta%', '%fbclid%'] as $pattern) {
                    $group->orWhere('utm_source', 'like', $pattern)
                        ->orWhere('utm_medium', 'like', $pattern)
                        ->orWhere('referrer', 'like', $pattern)
                        ->orWhere('landing_path', 'like', $pattern);
                }

                $group->orWhereIn('utm_source', ['meta', 'fb', 'facebook', 'instagram', 'ig']);
            });

            return;
        }

        $patterns = match ($source) {
            AttributionTaxonomy::SOURCE_WHATSAPP => ['%whatsapp%', '%wa.me%', '%aisensy%'],
            AttributionTaxonomy::SOURCE_GOOGLE => ['%google%', '%gclid%', '%wbraid%', '%gbraid%'],
            AttributionTaxonomy::SOURCE_YOUTUBE => ['%youtube%', '%youtu.be%'],
            AttributionTaxonomy::SOURCE_STAFF => ['staff'],
            AttributionTaxonomy::SOURCE_WORDPRESS => ['wordpress'],
            default => [],
        };

        if ($patterns === []) {
            $query->where('utm_source', $source);

            return;
        }

        $query->where(function (Builder $group) use ($patterns) {
            foreach ($patterns as $pattern) {
                if ($pattern === 'staff' || $pattern === 'wordpress') {
                    $group->orWhere('utm_source', $pattern);
                } else {
                    $group->orWhere('utm_source', 'like', $pattern)
                        ->orWhere('utm_medium', 'like', $pattern)
                        ->orWhere('referrer', 'like', $pattern)
                        ->orWhere('landing_path', 'like', $pattern);
                }
            }
        });
    }

    private static function applyLegacyPlatformFilter(Builder $query, string $platform): void
    {
        $patterns = match ($platform) {
            AttributionTaxonomy::PLATFORM_INSTAGRAM => ['%instagram%', '%igshid%', '%ig_%'],
            AttributionTaxonomy::PLATFORM_FACEBOOK => ['%facebook%', '%fbclid%', '%fb.%', '%meta%'],
            AttributionTaxonomy::PLATFORM_MESSENGER => ['%messenger%'],
            AttributionTaxonomy::PLATFORM_AUDIENCE_NETWORK => ['%audience_network%', '%audience network%'],
            default => [],
        };

        $query->where(function (Builder $group) use ($patterns, $platform) {
            foreach ($patterns as $pattern) {
                $group->orWhere('utm_source', 'like', $pattern)
                    ->orWhere('utm_medium', 'like', $pattern)
                    ->orWhere('referrer', 'like', $pattern)
                    ->orWhere('landing_path', 'like', $pattern);
            }

            if ($platform === AttributionTaxonomy::PLATFORM_INSTAGRAM) {
                $group->orWhereIn('utm_source', ['instagram', 'ig']);
            }

            if ($platform === AttributionTaxonomy::PLATFORM_FACEBOOK) {
                $group->orWhereIn('utm_source', ['facebook', 'fb', 'meta']);
            }
        });
    }

    private static function applyLegacyOrganicFilter(Builder $query): void
    {
        $query->where(function (Builder $organic) {
            $organic->where(function (Builder $blank) {
                $blank->where(function (Builder $source) {
                    $source->whereNull('utm_source')->orWhere('utm_source', '');
                })->where(function (Builder $medium) {
                    $medium->whereNull('utm_medium')->orWhere('utm_medium', '');
                });
            })->orWhereIn('utm_source', ['organic', 'direct', '(direct)', 'none']);
        })->where(function (Builder $notTrackedClick) {
            $notTrackedClick
                ->where(function (Builder $ref) {
                    $ref->whereNull('referrer')
                        ->orWhere(function (Builder $cleanRef) {
                            $cleanRef->where('referrer', 'not like', '%fbclid%')
                                ->where('referrer', 'not like', '%facebook%')
                                ->where('referrer', 'not like', '%instagram%')
                                ->where('referrer', 'not like', '%gclid%')
                                ->where('referrer', 'not like', '%google.%');
                        });
                })
                ->where(function (Builder $path) {
                    $path->whereNull('landing_path')
                        ->orWhere(function (Builder $cleanPath) {
                            $cleanPath->where('landing_path', 'not like', '%fbclid%')
                                ->where('landing_path', 'not like', '%gclid%');
                        });
                });
        });
    }

    /**
     * Stamp browser attribution onto the order at checkout start.
     *
     * @param  array{
     *     utm_source?: ?string,
     *     utm_medium?: ?string,
     *     utm_campaign?: ?string,
     *     utm_content?: ?string,
     *     utm_term?: ?string,
     *     platform?: ?string,
     *     placement?: ?string,
     *     device_type?: ?string,
     *     referrer?: ?string,
     *     landing_path?: ?string
     * }  $snapshot
     */
    public function stampFromCheckout(DonationOrder $order, array $snapshot): void
    {
        $order->loadMissing('items');

        $campaignId = $order->source_campaign_id
            ?? $order->items->first()?->donation_campaign_id;

        $payload = $this->attributionPayload($snapshot, $campaignId);

        if ($payload === []) {
            return;
        }

        $order->forceFill($payload)->save();
    }

    /**
     * Recurring donations are charged by Razorpay long after the browser session ends,
     * so the subscription is the only place the original attribution can live.
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function stampSubscriptionFromCheckout(DonationSubscription $subscription, array $snapshot): void
    {
        $payload = $this->attributionPayload($snapshot, $subscription->donation_campaign_id);

        // source_campaign_id is a donation_orders column only.
        unset($payload['source_campaign_id']);

        if ($payload === []) {
            return;
        }

        $subscription->forceFill($payload)->save();
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, int|string|null>
     */
    private function attributionPayload(array $snapshot, ?int $campaignId): array
    {
        $snapshot = AttributionParameters::decodeTrackingPayload($snapshot);

        $requestedChannel = $this->nullableLimited($snapshot['source_channel'] ?? null, 32);
        $channel = match (true) {
            $campaignId !== null => self::CHANNEL_CAMPAIGN,
            $requestedChannel === self::CHANNEL_WORDPRESS => self::CHANNEL_WORDPRESS,
            default => self::CHANNEL_WEB,
        };

        $referrer = $this->nullableLimited($snapshot['referrer'] ?? null, 512);
        $landingPath = $this->nullableLimited($snapshot['landing_path'] ?? null, 255);

        $payload = array_filter([
            'source_channel' => $channel,
            'source_campaign_id' => $campaignId,
            'utm_source' => $this->nullableLimited($snapshot['utm_source'] ?? null, 120),
            'utm_medium' => $this->nullableLimited($snapshot['utm_medium'] ?? null, 120),
            'utm_campaign' => $this->nullableLimited($snapshot['utm_campaign'] ?? null, 120),
            'utm_content' => $this->nullableLimited($snapshot['utm_content'] ?? null, 120),
            'utm_term' => $this->nullableLimited($snapshot['utm_term'] ?? null, 120),
            'referrer' => $referrer,
            'landing_path' => $landingPath,
            'device_type' => $this->nullableLimited($snapshot['device_type'] ?? null, 32),
        ], fn ($value) => $value !== null && $value !== '');

        $payload = array_merge($payload, AttributionNormalizer::normalizedPayload([
            'utm_source' => $payload['utm_source'] ?? null,
            'utm_medium' => $payload['utm_medium'] ?? null,
            'utm_campaign' => $payload['utm_campaign'] ?? null,
            'utm_content' => $payload['utm_content'] ?? null,
            'utm_term' => $payload['utm_term'] ?? null,
            'platform' => $snapshot['platform'] ?? null,
            'placement' => $snapshot['placement'] ?? null,
            'referrer' => $referrer,
            'landing_path' => $landingPath,
        ]));

        return array_merge($payload, AttributionParameters::columnsFromPayload([
            'sid' => $snapshot['sid'] ?? $snapshot['pid'] ?? null,
            'pid' => $snapshot['pid'] ?? null,
            'utm_id' => $snapshot['utm_id'] ?? null,
            'aid' => $snapshot['aid'] ?? null,
            'utm_term' => $snapshot['utm_term'] ?? null,
            'utm_source' => $payload['utm_source'] ?? null,
            'utm_content' => $payload['utm_content'] ?? null,
        ]));
    }

    /**
     * Resolve partner + Meta ad identifiers for an order from values already stored on it.
     */
    public static function resolveIdentifierColumns(DonationOrder $order): bool
    {
        $resolved = AttributionParameters::columnsFromPayload([
            'sid' => $order->partner_code,
            'utm_id' => $order->meta_campaign_id,
            'utm_term' => $order->meta_adset_id,
            'aid' => $order->meta_ad_id,
            'utm_source' => $order->utm_source,
            'utm_content' => $order->utm_content,
        ]);

        if ($resolved === []) {
            return false;
        }

        $before = $order->only(array_keys($resolved));
        $order->forceFill($resolved)->save();

        return $before !== $resolved;
    }

    public function stampChannel(DonationOrder $order, string $channel): void
    {
        if ($order->source_channel) {
            return;
        }

        $order->forceFill(['source_channel' => $channel])->save();
    }

    /**
     * After payment: backfill missing attribution from checkout_started, then ensure channel.
     */
    public function ensureOnPaid(DonationOrder $order): void
    {
        $order->refresh();

        $this->backfillMissingFromCheckoutEvent($order);
        $order->refresh();

        if (! $order->source_campaign_id) {
            $order->loadMissing('items');
            $campaignId = $order->items->first()?->donation_campaign_id;

            if ($campaignId) {
                $order->forceFill(['source_campaign_id' => $campaignId])->save();
                $order->refresh();
            }
        }

        if (! $order->source_channel) {
            $order->forceFill([
                'source_channel' => $this->resolveChannel($order),
            ])->save();
        }
    }

    public function backfillOrder(DonationOrder $order): bool
    {
        $before = $order->only([
            'source_channel',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_content',
            'utm_term',
            'attr_source',
            'attr_medium',
            'attr_platform',
            'attr_placement',
            'partner_user_id',
            'partner_code',
            'meta_campaign_id',
            'meta_adset_id',
            'meta_ad_id',
            'referrer',
            'landing_path',
            'device_type',
            'source_campaign_id',
        ]);

        $this->ensureOnPaid($order);
        self::normalizeOrderAttributes($order->fresh());
        self::resolveIdentifierColumns($order->fresh());
        $order->refresh();

        $after = $order->only(array_keys($before));

        return $before !== $after;
    }

    public function resolveChannel(DonationOrder $order): string
    {
        if (filled($order->source_channel)) {
            return (string) $order->source_channel;
        }

        if ($order->payment_provider === DonationOrder::PROVIDER_RAZORPAY_QR) {
            return self::CHANNEL_RAZORPAY_QR;
        }

        if ($order->payment_provider === DonationOrder::PROVIDER_OFFLINE) {
            return self::CHANNEL_OFFLINE;
        }

        if (filled($order->payment_link_id)) {
            return self::CHANNEL_PAYMENT_LINK;
        }

        $order->loadMissing('items');

        if ($order->source_campaign_id || $order->items->first()?->donation_campaign_id) {
            return self::CHANNEL_CAMPAIGN;
        }

        if (in_array($order->payment_provider, [
            DonationOrder::PROVIDER_RAZORPAY,
            DonationOrder::PROVIDER_CASHFREE,
            DonationOrder::PROVIDER_DANAMOJO,
        ], true)) {
            return self::CHANNEL_WEB;
        }

        return self::CHANNEL_UNKNOWN;
    }

    /**
     * @return array{
     *     channel: ?string,
     *     channel_label: string,
     *     utm_source: ?string,
     *     utm_medium: ?string,
     *     utm_campaign: ?string,
     *     utm_content: ?string,
     *     referrer: ?string,
     *     referrer_host: ?string,
     *     landing_path: ?string,
     *     device_type: ?string,
     *     campaign_name: ?string
     * }
     */
    public function detailPayload(DonationOrder|DonationSubscription $record): array
    {
        if ($record instanceof DonationOrder) {
            $record->loadMissing(['sourceCampaign', 'items.campaign', 'partner']);
            $campaignName = $record->sourceCampaign?->name
                ?? $record->items->first()?->campaign?->name;
        } else {
            $record->loadMissing(['campaign', 'partner']);
            $campaignName = $record->campaign?->name;
        }

        $referrer = $record->referrer;

        return [
            'channel' => $record->source_channel,
            'channel_label' => self::channelLabel($record->source_channel),
            'utm_source' => $record->utm_source,
            'utm_medium' => $record->utm_medium,
            'utm_campaign' => $record->utm_campaign,
            'utm_content' => $record->utm_content,
            'utm_term' => $record->utm_term,
            'attr_source' => $record->attr_source,
            'attr_medium' => $record->attr_medium,
            'attr_platform' => $record->attr_platform,
            'attr_placement' => $record->attr_placement,
            'attr_source_label' => self::trafficSourceLabel($record),
            'partner_code' => $record->partner_code,
            'partner_name' => $record->partner?->name,
            'meta_campaign_id' => $record->meta_campaign_id,
            'meta_adset_id' => $record->meta_adset_id,
            'meta_ad_id' => $record->meta_ad_id,
            'referrer' => $referrer,
            'referrer_host' => AdminAnalyticsData::normalizeReferrerHost($referrer),
            'landing_path' => $record->landing_path,
            'device_type' => $record->device_type,
            'ip_address' => $record instanceof DonationOrder ? $record->ip_address : null,
            'ip_country_code' => $record instanceof DonationOrder ? $record->ip_country_code : null,
            'ip_country_name' => $record instanceof DonationOrder ? $record->ip_country_name : null,
            'ip_region_name' => $record instanceof DonationOrder ? $record->ip_region_name : null,
            'ip_city' => $record instanceof DonationOrder ? $record->ip_city : null,
            'ip_postal_code' => $record instanceof DonationOrder ? $record->ip_postal_code : null,
            'ip_lat' => $record instanceof DonationOrder ? $record->ip_lat : null,
            'ip_lng' => $record instanceof DonationOrder ? $record->ip_lng : null,
            'ip_timezone' => $record instanceof DonationOrder ? $record->ip_timezone : null,
            'ip_asn' => $record instanceof DonationOrder ? $record->ip_asn : null,
            'ip_isp' => $record instanceof DonationOrder ? $record->ip_isp : null,
            'campaign_name' => $campaignName,
        ];
    }

    private function backfillMissingFromCheckoutEvent(DonationOrder $order): void
    {
        $event = AnalyticsEvent::query()
            ->where('donation_order_id', $order->id)
            ->where('event_type', AnalyticsEvent::TYPE_CHECKOUT_STARTED)
            ->latest('id')
            ->first();

        if ($event === null) {
            return;
        }

        $candidates = [
            'utm_source' => $this->nullableLimited($event->utm_source, 120),
            'utm_medium' => $this->nullableLimited($event->utm_medium, 120),
            'utm_campaign' => $this->nullableLimited($event->utm_campaign, 120),
            'utm_content' => $this->nullableLimited($event->utm_content ?? null, 120),
            'utm_term' => $this->nullableLimited($event->utm_term ?? null, 120),
            'referrer' => $this->nullableLimited($event->referrer, 512),
            'landing_path' => $this->nullableLimited($event->path, 255),
            'device_type' => $this->nullableLimited($event->device_type, 32),
            'partner_code' => $this->nullableLimited($event->partner_code ?? null, 40),
            'meta_campaign_id' => $this->nullableLimited($event->meta_campaign_id ?? null, 40),
            'meta_adset_id' => $this->nullableLimited($event->meta_adset_id ?? null, 40),
            'meta_ad_id' => $this->nullableLimited($event->meta_ad_id ?? null, 40),
        ];

        $payload = [];

        foreach ($candidates as $key => $value) {
            if ($value !== null && $value !== '' && blank($order->{$key})) {
                $payload[$key] = $value;
            }
        }

        if ($payload !== []) {
            $order->forceFill($payload)->save();
            $order->refresh();
        }

        self::normalizeOrderAttributes($order);
        self::resolveIdentifierColumns($order);
    }

    private function nullableLimited(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return Str::limit($trimmed, $limit, '');
    }
}

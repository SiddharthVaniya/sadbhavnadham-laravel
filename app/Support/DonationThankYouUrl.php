<?php

namespace App\Support;

use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use Illuminate\Support\Facades\URL;

class DonationThankYouUrl
{
    public const VALID_FOR_DAYS = 30;

    /**
     * Public receipt page on the Next.js site.
     * Query is signed for GET /api/donate/thank-you/{order} so the frontend proxy can load it.
     */
    public static function forOrder(DonationOrder $order): string
    {
        return self::frontendUrl(
            '/donate/thank-you/'.$order->order_uuid,
            self::forApiOrder($order),
        );
    }

    /**
     * Public recurring receipt page on the Next.js site.
     */
    public static function forSubscription(DonationSubscription $subscription): string
    {
        return self::frontendUrl(
            '/donate/thank-you/subscription/'.$subscription->subscription_uuid,
            self::forApiSubscription($subscription),
        );
    }

    public static function forLaravelPage(DonationOrder $order): string
    {
        return URL::temporarySignedRoute(
            'donate.thank-you',
            now()->addDays(self::VALID_FOR_DAYS),
            ['order' => $order],
        );
    }

    public static function forLaravelSubscriptionPage(DonationSubscription $subscription): string
    {
        return URL::temporarySignedRoute(
            'donate.thank-you.subscription',
            now()->addDays(self::VALID_FOR_DAYS),
            ['subscription' => $subscription],
        );
    }

    public static function forApiOrder(DonationOrder $order): string
    {
        return URL::temporarySignedRoute(
            'donate.api.thank-you',
            now()->addDays(self::VALID_FOR_DAYS),
            ['order' => $order],
        );
    }

    public static function forApiSubscription(DonationSubscription $subscription): string
    {
        return URL::temporarySignedRoute(
            'donate.api.thank-you.subscription',
            now()->addDays(self::VALID_FOR_DAYS),
            ['subscription' => $subscription],
        );
    }

    private static function frontendUrl(string $path, string $signedApiUrl): string
    {
        $query = parse_url($signedApiUrl, PHP_URL_QUERY);

        return DonationPublicFrontend::absolute(
            $path,
            self::queryPairs(is_string($query) ? $query : ''),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function queryPairs(string $query): array
    {
        if ($query === '') {
            return [];
        }

        parse_str($query, $pairs);

        $out = [];

        foreach ($pairs as $key => $value) {
            if (is_string($key) && is_scalar($value) && $value !== '') {
                $out[$key] = (string) $value;
            }
        }

        return $out;
    }
}

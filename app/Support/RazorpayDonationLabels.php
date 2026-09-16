<?php

namespace App\Support;

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationOrder;
use Illuminate\Support\Str;

class RazorpayDonationLabels
{
    public static function itemTitle(Cause $cause, ?CausePackage $package, ?string $requestedTitle = null): string
    {
        $title = trim((string) ($requestedTitle ?: ''));

        if ($title !== '' && ! self::isGenericCustomTitle($title, $cause)) {
            return Str::limit($title, 255);
        }

        if ($package) {
            return $package->title;
        }

        return 'Custom Donation';
    }

    public static function description(
        Cause $cause,
        ?CausePackage $package,
        ?string $requestedTitle = null,
    ): string {
        $title = trim((string) ($requestedTitle ?: ''));

        if ($title !== '' && ! self::isGenericCustomTitle($title, $cause)) {
            return Str::limit($title, 255);
        }

        if ($package) {
            return Str::limit("Donation: {$cause->title} - {$package->title}", 255);
        }

        return Str::limit("Donation: {$cause->title} - Custom Amount", 255);
    }

    /**
     * @return array<string, string>
     */
    public static function orderNotes(DonationOrder $order, Cause $cause, ?CausePackage $package): array
    {
        $notes = [
            'donation_order_id' => (string) $order->id,
            'cause' => $cause->slug,
            'cause_name' => Str::limit($cause->title, 255),
            'package_name' => $package ? Str::limit($package->title, 255) : '',
        ];

        if ($package) {
            $notes['cause_package_id'] = (string) $package->id;
        }

        return $notes;
    }

    public static function sheetPackageLabel(?int $causePackageId, ?string $title): string
    {
        if ($causePackageId === null) {
            return '';
        }

        return trim((string) $title);
    }

    private static function isGenericCustomTitle(string $title, Cause $cause): bool
    {
        $normalized = strtolower(trim($title));

        if (in_array($normalized, ['donation', 'custom donation', 'general donation', 'custom amount', 'monthly donation'], true)) {
            return true;
        }

        if ($cause->default_title && strcasecmp(trim($cause->default_title), $title) === 0) {
            return true;
        }

        return false;
    }
}

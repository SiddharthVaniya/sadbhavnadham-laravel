<?php

namespace App\Services;

use App\Models\Cause;
use App\Models\DonationOrder;
use App\Models\Donor;
use App\Models\Setting;
use Carbon\CarbonInterface;

class DonationWhatsAppPolicy
{
    public function shouldSendThankYou(DonationOrder $order): bool
    {
        if (! Setting::isEnabled(Setting::SEND_WHATSAPP_THANK_YOU)) {
            return false;
        }

        if (! $this->hasSendablePhone($order)) {
            return false;
        }

        $cause = $this->resolveCause($order);

        return $cause?->shouldSendThankYouWhatsApp() ?? false;
    }

    public function shouldSendCertificate(DonationOrder $order): bool
    {
        if (! Setting::isEnabled(Setting::SEND_DONATION_CERTIFICATE)) {
            return false;
        }

        if (! $this->hasSendablePhone($order)) {
            return false;
        }

        if (! $this->isWithinCertificateWhatsAppWindow($order)) {
            return false;
        }

        $cause = $this->resolveCause($order);

        return $cause?->shouldSendCertificateWhatsApp() ?? false;
    }

    /**
     * Skip auto-send / reconcile for donations paid before the configured cutoff.
     */
    private function isWithinCertificateWhatsAppWindow(DonationOrder $order): bool
    {
        $onlyAfter = trim((string) config('donation.certificate.whatsapp_only_after', ''));

        if ($onlyAfter === '') {
            return true;
        }

        try {
            $cutoff = \Illuminate\Support\Carbon::parse($onlyAfter);
        } catch (\Throwable) {
            return true;
        }

        $paidAt = $order->paid_at ?? $order->created_at;

        if ($paidAt === null) {
            return true;
        }

        return $paidAt->greaterThanOrEqualTo($cutoff);
    }

    public function shouldSendReceipt(DonationOrder $order): bool
    {
        if (! Setting::isEnabled(Setting::SEND_RECEIPT_WHATSAPP)) {
            return false;
        }

        if (! $this->hasSendablePhone($order)) {
            return false;
        }

        $cause = $this->resolveCause($order);

        return $cause?->shouldSendReceiptWhatsApp() ?? false;
    }

    public function shouldSendBirthday(Donor $donor, CarbonInterface|string|null $onDate = null, bool $force = false): bool
    {
        if (! $force && ! Setting::isEnabled(Setting::SEND_BIRTHDAY_WHATSAPP)) {
            return false;
        }

        if (! $this->hasSendablePhoneNumber($donor->phone)) {
            return false;
        }

        $date = $onDate === null
            ? now()->startOfDay()
            : \Illuminate\Support\Carbon::parse($onDate)->startOfDay();

        if (! $force) {
            if ($donor->date_of_birth === null) {
                return false;
            }

            if ((int) $donor->date_of_birth->month !== (int) $date->month
                || (int) $donor->date_of_birth->day !== (int) $date->day) {
                return false;
            }

            if ($donor->birthday_whatsapp_sent_on !== null
                && $donor->birthday_whatsapp_sent_on->isSameDay($date)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Shared phone rules for donation orders and donors: ≥10 digits, no letters,
     * no placeholder values such as "upi-…" or "u-…".
     */
    public function hasSendablePhoneNumber(?string $phone): bool
    {
        $phone = trim((string) $phone);

        if ($phone === '' || preg_match('/[a-z]/i', $phone)) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) >= 10;
    }

    /**
     * Auto-send on payment capture. Razorpay QR starts without a real donor
     * phone, so it never auto-notifies; admins can still force-send after editing.
     */
    private function hasSendablePhone(DonationOrder $order): bool
    {
        if ($order->payment_provider === DonationOrder::PROVIDER_RAZORPAY_QR) {
            return false;
        }

        return $this->hasSendablePhoneNumber($order->donor_phone);
    }

    private function resolveCause(DonationOrder $order): ?Cause
    {
        $order->loadMissing('items.causeModel');

        return $order->items->first()?->causeModel;
    }
}

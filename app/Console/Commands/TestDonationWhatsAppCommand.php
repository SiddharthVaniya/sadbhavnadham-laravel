<?php

namespace App\Console\Commands;

use App\Models\DonationOrder;
use App\Services\AiSensyService;
use App\Services\DonationCertificateService;
use App\Services\DonationWhatsAppPolicy;
use Illuminate\Console\Command;

class TestDonationWhatsAppCommand extends Command
{
    protected $signature = 'donations:send-whatsapp
                            {order : Donation order ID, order UUID, or Razorpay payment id (pay_...)}
                            {--force : Resend even if already marked sent}
                            {--thank-you-only : Send only the thank you message}
                            {--certificate-only : Send only the certificate message}';

    protected $description = 'Generate certificate (if enabled) and send thank-you/certificate WhatsApp for testing';

    public function handle(
        AiSensyService $aiSensyService,
        DonationCertificateService $donationCertificateService,
        DonationWhatsAppPolicy $donationWhatsAppPolicy,
    ): int {
        $order = $this->resolveOrder((string) $this->argument('order'));

        if (! $order) {
            $this->error('Donation order not found.');

            return self::FAILURE;
        }

        if (! $order->isPaid()) {
            $this->error('Order is not paid. Only paid donations can be used for WhatsApp testing.');

            return self::FAILURE;
        }

        if (empty($order->donor_phone)) {
            $this->error('Order has no donor phone number.');

            return self::FAILURE;
        }

        $order->loadMissing('items.causeModel');
        $cause = $order->items->first()?->causeModel;

        $this->info("Order #{$order->id} — {$order->donor_name} ({$order->donor_phone})");
        $this->line('Cause: '.($cause?->title ?? 'Unknown'));

        if ($this->option('force')) {
            $order->update([
                'whatsapp_sent_at' => null,
                'certificate_whatsapp_sent_at' => null,
            ]);
            $order->refresh();
            $this->warn('Cleared whatsapp_sent_at and certificate_whatsapp_sent_at (--force).');
        }

        $sendThankYou = ! $this->option('certificate-only');
        $sendCertificate = ! $this->option('thank-you-only');
        $sentSomething = false;

        if ($sendThankYou) {
            $sentSomething = $this->sendThankYou($order, $aiSensyService, $donationWhatsAppPolicy) || $sentSomething;
        } else {
            $this->line('Skipping thank you (--certificate-only).');
        }

        if ($sendCertificate) {
            $sentSomething = $this->sendCertificate(
                $order,
                $aiSensyService,
                $donationCertificateService,
                $donationWhatsAppPolicy,
            ) || $sentSomething;
        } else {
            $this->line('Skipping certificate (--thank-you-only).');
        }

        if (! $sentSomething) {
            $this->warn('Nothing was sent. Check Settings toggles, cause WhatsApp toggles, AiSensy campaigns, and storage/logs/laravel.log.');

            return self::FAILURE;
        }

        $this->info('Done. Check WhatsApp on '.$order->donor_phone.'.');

        return self::SUCCESS;
    }

    private function sendThankYou(
        DonationOrder $order,
        AiSensyService $aiSensyService,
        DonationWhatsAppPolicy $donationWhatsAppPolicy,
    ): bool {
        if (! $donationWhatsAppPolicy->shouldSendThankYou($order)) {
            $this->line('Thank you: skipped (disabled in Settings or on this cause).');

            return false;
        }

        if ($order->whatsapp_sent_at) {
            $this->line('Thank you: skipped (already sent at '.$order->whatsapp_sent_at->toDateTimeString().'). Use --force to resend.');

            return false;
        }

        if (! $aiSensyService->sendThankYouWhatsApp($order)) {
            $this->error('Thank you: AiSensy request failed. See storage/logs/laravel.log.');

            return false;
        }

        $order->update(['whatsapp_sent_at' => now()]);
        $this->info('Thank you: sent.');

        return true;
    }

    private function sendCertificate(
        DonationOrder $order,
        AiSensyService $aiSensyService,
        DonationCertificateService $donationCertificateService,
        DonationWhatsAppPolicy $donationWhatsAppPolicy,
    ): bool {
        if (! $donationWhatsAppPolicy->shouldSendCertificate($order)) {
            $this->line('Certificate: skipped (disabled in Settings or on this cause).');

            return false;
        }

        if ($order->certificate_whatsapp_sent_at) {
            $this->line('Certificate: skipped (already sent at '.$order->certificate_whatsapp_sent_at->toDateTimeString().'). Use --force to resend.');

            return false;
        }

        if (! $order->paid_at) {
            $order->update(['paid_at' => now()]);
            $this->warn('Certificate: order had no paid_at; using now for the certificate date.');
        }

        $certificateUrl = $donationCertificateService->whatsappMediaUrl($order, (bool) $this->option('force'));

        if ($certificateUrl) {
            $this->info('Certificate media: '.$certificateUrl);
        } else {
            $this->warn('Certificate PNG could not be generated; will try fallback image from cause if set.');
        }

        if (! $aiSensyService->sendCertificateWhatsApp($order)) {
            $this->error('Certificate: AiSensy request failed. See storage/logs/laravel.log.');

            return false;
        }

        $order->update(['certificate_whatsapp_sent_at' => now()]);
        $this->info('Certificate WhatsApp: sent.');

        return true;
    }

    private function resolveOrder(string $reference): ?DonationOrder
    {
        if (ctype_digit($reference)) {
            return DonationOrder::query()->find((int) $reference);
        }

        return DonationOrder::query()
            ->where('order_uuid', $reference)
            ->orWhere('provider_payment_id', $reference)
            ->orWhere('provider_order_id', $reference)
            ->first();
    }
}

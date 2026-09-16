<?php

namespace App\Console\Commands;

use App\Models\DonationOrder;
use App\Services\DonationCertificateService;
use Illuminate\Console\Command;

class GenerateDonationCertificateCommand extends Command
{
    protected $signature = 'donations:certificate {order : Donation order ID} {--force : Regenerate even if a certificate already exists}';

    protected $description = 'Generate a sanman patra certificate PNG (does not send WhatsApp; use donations:send-whatsapp for that)';

    public function handle(DonationCertificateService $donationCertificateService): int
    {
        $order = DonationOrder::query()->find($this->argument('order'));

        if (! $order) {
            $this->error('Donation order not found.');

            return self::FAILURE;
        }

        if (! $order->paid_at) {
            $order->paid_at = now();
            $this->warn('Order has no paid_at; using today for the certificate date.');
        }

        $url = $donationCertificateService->generate($order, (bool) $this->option('force'));

        if (! $url) {
            $this->error('Certificate could not be generated. Check storage/logs/laravel.log and confirm template + font exist.');

            return self::FAILURE;
        }

        $this->info('Certificate generated successfully.');
        $this->line('Donor: '.$donationCertificateService->donorDisplayName($order));
        $this->line('Date: '.$donationCertificateService->formattedDateLine($order));
        $this->line('URL: '.$url);
        $this->line('PNG: storage/app/public/certificates/sanman-'.$order->id.'.png');

        return self::SUCCESS;
    }
}

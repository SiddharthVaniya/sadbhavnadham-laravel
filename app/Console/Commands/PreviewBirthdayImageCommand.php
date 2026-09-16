<?php

namespace App\Console\Commands;

use App\Models\Donor;
use App\Models\Setting;
use App\Services\BirthdayImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PreviewBirthdayImageCommand extends Command
{
    protected $signature = 'donors:preview-birthday-image
                            {donor_id? : Existing donor ID to use}
                            {--name= : Donor name for a temporary test donor (used when donor_id is omitted)}
                            {--force : Regenerate the image even if one already exists}';

    protected $description = 'Generate a birthday image for local/live testing and print its public URL';

    public function handle(BirthdayImageService $birthdayImageService): int
    {
        $template = (string) config('donation.birthday.template');
        $fonts = array_filter([
            (string) config('donation.birthday.font'),
            (string) config('donation.birthday.font_regular'),
            (string) config('donation.birthday.font_gujarati'),
            (string) config('donation.birthday.font_gujarati_regular'),
            (string) config('donation.birthday.font_devanagari'),
            (string) config('donation.birthday.font_devanagari_regular'),
        ]);

        if (! File::exists($template)) {
            $this->error('Template missing: '.$template);

            return self::FAILURE;
        }

        foreach ($fonts as $font) {
            if (! File::exists($font)) {
                $this->warn('Font missing (may affect some names): '.$font);
            }
        }

        $this->line('Template: '.$template);
        $this->line('Fonts checked: '.count($fonts));

        $temporary = false;
        $donorId = $this->argument('donor_id');

        if ($donorId !== null) {
            $donor = Donor::query()->find($donorId);

            if ($donor === null) {
                $this->error('Donor not found: '.$donorId);

                return self::FAILURE;
            }
        } else {
            $name = trim((string) $this->option('name'));

            if ($name === '') {
                $name = 'Ronak Joshi';
            }

            $donor = Donor::query()->create([
                'name' => $name,
                'email' => 'birthday-preview-'.uniqid().'@example.test',
                'phone' => '9'.substr((string) time(), -9),
                'date_of_birth' => now()->subYears(30)->toDateString(),
                'country' => 'INDIA',
                'country_code' => 'IN',
                'consent_indian_citizen' => true,
            ]);
            $temporary = true;
            $this->warn('Created temporary donor #'.$donor->id.' ('.$donor->name.') for preview.');
        }

        if (! Setting::isEnabled(Setting::SEND_BIRTHDAY_WHATSAPP)) {
            $this->warn('Birthday WhatsApp setting is OFF — preview still generates the image.');
        }

        $url = $birthdayImageService->generate($donor, (bool) $this->option('force'), true);

        if ($url === null) {
            $this->error('Image generation failed. Check logs, Imagick/pdftoppm, and template/font paths.');

            if ($temporary) {
                $donor->delete();
            }

            return self::FAILURE;
        }

        $this->info('OK — birthday image generated.');
        $this->line('Donor: #'.$donor->id.' — '.$birthdayImageService->donorDisplayName($donor));
        $this->line('Public URL: '.$url);
        $this->line('Open that URL in browser (HTTPS on live). If 404, run: php artisan storage:link');

        return self::SUCCESS;
    }
}

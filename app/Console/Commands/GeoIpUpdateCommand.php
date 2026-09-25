<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

class GeoIpUpdateCommand extends Command
{
    protected $signature = 'geoip:update
                            {--month= : YYYY-MM edition (defaults to current month)}
                            {--force : Replace existing MMDB files even if present}';

    protected $description = 'Download DB-IP City Lite and ASN Lite MMDB databases';

    public function handle(): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');

        if (! preg_match('/^\d{4}-\d{2}$/', (string) $month)) {
            $this->error('Invalid --month. Use YYYY-MM.');

            return self::FAILURE;
        }

        $dir = dirname((string) config('geoip.city_mmdb'));
        File::ensureDirectoryExists($dir);

        $targets = [
            'city' => [
                'url' => str_replace('{YYYY-MM}', $month, (string) config('geoip.city_download_url')),
                'path' => (string) config('geoip.city_mmdb'),
            ],
            'asn' => [
                'url' => str_replace('{YYYY-MM}', $month, (string) config('geoip.asn_download_url')),
                'path' => (string) config('geoip.asn_mmdb'),
            ],
        ];

        foreach ($targets as $label => $target) {
            if (! $this->option('force') && is_file($target['path']) && filesize($target['path']) > 0) {
                $this->line("Skipping {$label}: already present at {$target['path']} (use --force to replace).");

                continue;
            }

            $this->info("Downloading {$label} MMDB ({$month})…");

            try {
                $response = Http::timeout(120)
                    ->withOptions(['stream' => true])
                    ->get($target['url']);

                if (! $response->successful()) {
                    $this->error("Failed to download {$label}: HTTP ".$response->status());

                    return self::FAILURE;
                }

                $tmpGz = $target['path'].'.tmp.gz';
                $tmpMmdb = $target['path'].'.tmp';

                File::put($tmpGz, $response->body());

                $gz = @gzopen($tmpGz, 'rb');
                if ($gz === false) {
                    File::delete($tmpGz);
                    $this->error("Could not open gzip stream for {$label}.");

                    return self::FAILURE;
                }

                $out = fopen($tmpMmdb, 'wb');
                if ($out === false) {
                    gzclose($gz);
                    File::delete($tmpGz);
                    $this->error("Could not write temp MMDB for {$label}.");

                    return self::FAILURE;
                }

                while (! gzeof($gz)) {
                    $chunk = gzread($gz, 1024 * 1024);
                    if ($chunk === false) {
                        break;
                    }
                    fwrite($out, $chunk);
                }

                gzclose($gz);
                fclose($out);
                File::delete($tmpGz);

                if (! is_file($tmpMmdb) || filesize($tmpMmdb) < 1024) {
                    File::delete($tmpMmdb);
                    $this->error("Downloaded {$label} file looks empty or invalid.");

                    return self::FAILURE;
                }

                File::move($tmpMmdb, $target['path']);
                $this->info("Saved {$label} → {$target['path']}");
            } catch (Throwable $e) {
                $this->error("{$label} download failed: ".$e->getMessage());

                return self::FAILURE;
            }
        }

        $this->info('GeoIP databases are up to date.');

        return self::SUCCESS;
    }
}

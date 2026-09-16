<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class DompdfGujaratiFont
{
    public const FAMILY = 'noto sans gujarati';

    /**
     * Ensure Noto Sans Gujarati is available for DomPDF and return DomPDF option overrides.
     *
     * @return array{fontDir: string, fontCache: string, defaultFont: string, isFontSubsettingEnabled: bool, isRemoteEnabled: bool, chroot: string}
     */
    public static function options(): array
    {
        $fontDir = storage_path('fonts');
        $fontCache = storage_path('fonts/cache');
        $regularDest = $fontDir.'/NotoSansGujarati-Regular.ttf';
        $boldDest = $fontDir.'/NotoSansGujarati-Bold.ttf';

        File::ensureDirectoryExists($fontDir);
        File::ensureDirectoryExists($fontCache);

        $regularSource = (string) config('donation.certificate.font', public_path('fonts/NotoSansGujarati-Regular.ttf'));
        $boldSource = (string) config('donation.certificate.font_bold', public_path('fonts/NotoSansGujarati-Bold.ttf'));

        if (File::exists($regularSource) && ! File::exists($regularDest)) {
            File::copy($regularSource, $regularDest);
        }

        if (File::exists($boldSource) && ! File::exists($boldDest)) {
            File::copy($boldSource, $boldDest);
        }

        if (File::exists($regularDest)) {
            self::register($regularDest, File::exists($boldDest) ? $boldDest : null);
        }

        return [
            'fontDir' => $fontDir,
            'fontCache' => $fontCache,
            'defaultFont' => self::FAMILY,
            // Subsetting drops Gujarati glyphs; keep the full font embedded.
            'isFontSubsettingEnabled' => false,
            'isRemoteEnabled' => false,
            'chroot' => base_path(),
        ];
    }

    private static function register(string $fontPath, ?string $boldFontPath = null): void
    {
        $fontDir = dirname($fontPath);
        $fontCache = storage_path('fonts/cache');

        File::ensureDirectoryExists($fontDir);
        File::ensureDirectoryExists($fontCache);

        $installedFontsPath = $fontDir.'/installed-fonts.json';
        $hasUfm = collect(glob($fontDir.'/*.ufm') ?: [])
            ->contains(fn (string $path): bool => str_contains(strtolower($path), 'noto')
                || str_contains(strtolower($path), 'gujarati'));

        if ($hasUfm && File::exists($installedFontsPath)) {
            $installed = json_decode(File::get($installedFontsPath), true) ?? [];

            if (isset($installed[self::FAMILY]['normal'], $installed[self::FAMILY]['bold'])) {
                return;
            }
        }

        $options = new Options;
        $options->set('fontDir', $fontDir);
        $options->set('fontCache', $fontCache);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $fontUri = str_replace('\\', '/', $fontPath);
        $boldFontUri = str_replace('\\', '/', $boldFontPath ?: $fontPath);

        $registeredNormal = $dompdf->getFontMetrics()->registerFont([
            'family' => 'Noto Sans Gujarati',
            'style' => 'normal',
            'weight' => 'normal',
        ], $fontUri);

        $registeredBold = $dompdf->getFontMetrics()->registerFont([
            'family' => 'Noto Sans Gujarati',
            'style' => 'normal',
            'weight' => 'bold',
        ], $boldFontUri);

        if (! $registeredNormal || ! $registeredBold) {
            Log::warning('DomPDF Gujarati font registration failed', [
                'regular' => $fontUri,
                'bold' => $boldFontUri,
                'registered_normal' => $registeredNormal,
                'registered_bold' => $registeredBold,
            ]);
        }
    }
}

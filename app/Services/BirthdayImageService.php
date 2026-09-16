<?php

namespace App\Services;

use App\Models\Donor;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BirthdayImageService
{
    public function generate(Donor $donor, bool $force = false, bool $ignoreToggle = false): ?string
    {
        if (! config('donation.birthday.enabled', true)) {
            return null;
        }

        if (! $ignoreToggle && ! Setting::isEnabled(Setting::SEND_BIRTHDAY_WHATSAPP)) {
            return null;
        }

        $templatePath = (string) config('donation.birthday.template');
        $donorName = $this->donorDisplayName($donor);
        $typography = $this->resolveNameTypography($donorName);
        $fontPath = $typography['bold_path'];

        if (! File::exists($templatePath) || ! File::exists($fontPath)) {
            Log::warning('Birthday image assets missing', [
                'donor_id' => $donor->id,
                'template_exists' => File::exists($templatePath),
                'font_exists' => File::exists($fontPath),
                'font_path' => $fontPath,
                'font_family' => $typography['family'],
            ]);

            return null;
        }

        $pngRelativePath = $this->storagePath($donor, 'png');
        $pngAbsolutePath = Storage::disk('public')->path($pngRelativePath);

        if (! $force && Storage::disk('public')->exists($pngRelativePath) && ! $this->shouldRegenerate($templatePath, $pngAbsolutePath)) {
            return $this->publicUrl($pngRelativePath);
        }

        $imageSize = @getimagesize($templatePath);

        if ($imageSize === false) {
            Log::error('Birthday template dimensions could not be read', [
                'donor_id' => $donor->id,
                'template' => $templatePath,
            ]);

            return null;
        }

        [$pixelWidth, $pixelHeight] = $imageSize;
        $pageWidthPt = $pixelWidth * 72 / 96;
        $pageHeightPt = $pixelHeight * 72 / 96;

        $fontDir = storage_path('fonts');
        $fontCache = storage_path('fonts/cache');
        File::ensureDirectoryExists($fontDir);
        File::ensureDirectoryExists($fontCache);

        $this->ensureBirthdayFontsInstalled($fontDir);
        $this->ensureDompdfFontFamily(
            $typography['family'],
            $typography['regular_path'],
            $typography['bold_path'],
        );

        $nameConfig = (array) config('donation.birthday.name', []);
        $nameSizePt = (float) ($nameConfig['size_pt'] ?? 36);
        $maxWidthPercent = (float) ($nameConfig['max_width_percent'] ?? 78);
        $maxWidthPt = $pageWidthPt * ($maxWidthPercent / 100);
        $wrap = mb_strlen($donorName) > 28;

        if ($wrap) {
            $nameSizePt = max((float) ($nameConfig['min_size_pt'] ?? 24), $nameSizePt * 0.75);
        }

        $mime = is_array($imageSize) && isset($imageSize['mime'])
            ? (string) $imageSize['mime']
            : 'image/png';

        $pdf = Pdf::loadView('birthdays.card', [
            'donorName' => $donorName,
            'templateImage' => 'data:'.$mime.';base64,'.base64_encode(File::get($templatePath)),
            'pageWidthPt' => $pageWidthPt,
            'pageHeightPt' => $pageHeightPt,
            'nameTopPercent' => (float) ($nameConfig['top_percent'] ?? 42.8),
            'nameSizePt' => $nameSizePt,
            'nameMaxWidthPt' => $maxWidthPt,
            'nameWrap' => $wrap,
            'nameColor' => $this->normalizeColor((string) ($nameConfig['color'] ?? ''), '#C4A035'),
            'nameFontFamily' => $typography['family'],
        ])->setPaper([0, 0, $pageWidthPt, $pageHeightPt])->setOptions([
            'isRemoteEnabled' => true,
            'fontDir' => $fontDir,
            'fontCache' => $fontCache,
            'defaultFont' => $typography['family'],
            // Subsetting can drop Indic glyphs (names/date labels → boxes).
            'isFontSubsettingEnabled' => false,
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'defaultMediaType' => 'screen',
            'dpi' => 96,
        ]);

        Storage::disk('public')->makeDirectory((string) config('donation.birthday.storage_directory', 'birthdays'));

        $tempPdfPath = tempnam(sys_get_temp_dir(), 'birthday-');

        if ($tempPdfPath === false) {
            return null;
        }

        $tempPdfPath .= '.pdf';

        try {
            File::put($tempPdfPath, $pdf->output());

            if (! $this->convertPdfToPng($tempPdfPath, $pngAbsolutePath, $pixelWidth, $pixelHeight)) {
                if (File::exists($pngAbsolutePath)) {
                    File::delete($pngAbsolutePath);
                }

                Log::warning('Birthday PNG conversion unavailable', [
                    'donor_id' => $donor->id,
                ]);

                return null;
            }

            $this->deleteWhatsAppJpeg($donor);

            return $this->publicUrl($pngRelativePath);
        } finally {
            if (File::exists($tempPdfPath)) {
                File::delete($tempPdfPath);
            }
        }
    }

    public function whatsappMediaUrl(Donor $donor, bool $force = false, bool $ignoreToggle = false): ?string
    {
        if ($this->generate($donor, $force, $ignoreToggle) === null && ! Storage::disk('public')->exists($this->storagePath($donor, 'png'))) {
            return null;
        }

        $pngRelativePath = $this->storagePath($donor, 'png');

        if (! Storage::disk('public')->exists($pngRelativePath)) {
            return null;
        }

        $maxBytes = max(500_000, (int) config('donation.birthday.whatsapp_max_bytes', 4_500_000));

        if ((bool) config('donation.birthday.whatsapp_prefer_png', true)
            && Storage::disk('public')->size($pngRelativePath) <= $maxBytes) {
            return $this->publicUrl($pngRelativePath);
        }

        $jpegRelativePath = $this->whatsappStoragePath($donor);

        if ($force && Storage::disk('public')->exists($jpegRelativePath)) {
            Storage::disk('public')->delete($jpegRelativePath);
        }

        if (! Storage::disk('public')->exists($jpegRelativePath)) {
            if (! $this->createWhatsAppJpeg($pngRelativePath, $jpegRelativePath)) {
                return $this->publicUrl($pngRelativePath);
            }
        }

        return $this->publicUrl($jpegRelativePath);
    }

    public function donorDisplayName(Donor $donor): string
    {
        $name = preg_replace('/\s+/u', ' ', (string) $donor->name) ?? '';
        $name = trim($name);

        if ($name === '') {
            return 'Friend';
        }

        // Keep Gujarati / Hindi names as stored — title-case can break Indic text.
        if (preg_match('/\p{Gujarati}|\p{Devanagari}/u', $name) === 1) {
            return $name;
        }

        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

        if ((bool) config('donation.birthday.name.uppercase', false)) {
            $name = mb_strtoupper($name, 'UTF-8');
        }

        return $name;
    }

    /**
     * @return array{family: string, bold_path: string, regular_path: string}
     */
    public function resolveNameTypography(string $name): array
    {
        if (preg_match('/\p{Gujarati}/u', $name) === 1) {
            return [
                'family' => 'noto sans gujarati',
                'bold_path' => (string) config('donation.birthday.font_gujarati'),
                'regular_path' => (string) config('donation.birthday.font_gujarati_regular'),
            ];
        }

        if (preg_match('/\p{Devanagari}/u', $name) === 1) {
            return [
                'family' => 'noto sans devanagari',
                'bold_path' => (string) config('donation.birthday.font_devanagari'),
                'regular_path' => (string) config('donation.birthday.font_devanagari_regular'),
            ];
        }

        return [
            'family' => 'poppins',
            'bold_path' => (string) config('donation.birthday.font_bold', config('donation.birthday.font')),
            'regular_path' => (string) config('donation.birthday.font_regular'),
        ];
    }

    private function ensureBirthdayFontsInstalled(string $fontDir): void
    {
        $copies = [
            (string) config('donation.birthday.font') => $fontDir.'/Poppins-Bold.ttf',
            (string) config('donation.birthday.font_regular') => $fontDir.'/Poppins-Regular.ttf',
            (string) config('donation.birthday.font_bold') => $fontDir.'/Poppins-Bold.ttf',
            (string) config('donation.birthday.font_gujarati') => $fontDir.'/NotoSansGujarati-Bold.ttf',
            (string) config('donation.birthday.font_gujarati_regular') => $fontDir.'/NotoSansGujarati-Regular.ttf',
            (string) config('donation.birthday.font_devanagari') => $fontDir.'/NotoSansDevanagari-Bold.ttf',
            (string) config('donation.birthday.font_devanagari_regular') => $fontDir.'/NotoSansDevanagari-Regular.ttf',
        ];

        foreach ($copies as $source => $destination) {
            if ($source === '' || ! File::exists($source) || ! $this->isValidTrueTypeFont($source)) {
                continue;
            }

            if (! File::exists($destination) || File::lastModified($source) > File::lastModified($destination)) {
                File::copy($source, $destination);
            }
        }
    }

    private function ensureDompdfFontFamily(string $family, string $regularPath, string $boldPath): void
    {
        $fontDir = storage_path('fonts');
        $fontCache = storage_path('fonts/cache');

        File::ensureDirectoryExists($fontDir);
        File::ensureDirectoryExists($fontCache);

        $regularDest = $this->dompdfFontDestination($fontDir, $family, 'regular');
        $boldDest = $this->dompdfFontDestination($fontDir, $family, 'bold');

        if (File::exists($regularPath) && $this->isValidTrueTypeFont($regularPath)) {
            File::copy($regularPath, $regularDest);
        }

        if (File::exists($boldPath) && $this->isValidTrueTypeFont($boldPath)) {
            File::copy($boldPath, $boldDest);
        } elseif (File::exists($regularDest)) {
            File::copy($regularDest, $boldDest);
        }

        if (! File::exists($regularDest)) {
            return;
        }

        $installedFontsPath = $fontDir.'/installed-fonts.json';
        $familyKey = strtolower(trim($family));
        $needle = strtolower(str_replace(' ', '', $familyKey));

        $hasUfm = collect(glob($fontDir.'/*.ufm') ?: [])
            ->contains(fn (string $path): bool => str_contains(strtolower(basename($path)), $needle));

        if ($hasUfm && File::exists($installedFontsPath)) {
            $installed = json_decode(File::get($installedFontsPath), true) ?? [];

            if (isset($installed[$familyKey]['normal'], $installed[$familyKey]['bold'])) {
                return;
            }
        }

        $options = new \Dompdf\Options;
        $options->set('fontDir', $fontDir);
        $options->set('fontCache', $fontCache);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', base_path());

        $dompdf = new \Dompdf\Dompdf($options);
        // DomPDF accepts absolute filesystem paths. The file:///D:/… form fails chroot
        // validation on Windows; plain paths work on both Windows and Linux.
        $regularUri = str_replace('\\', '/', $regularDest);
        $boldUri = str_replace('\\', '/', File::exists($boldDest) ? $boldDest : $regularDest);

        $registeredNormal = $dompdf->getFontMetrics()->registerFont([
            'family' => $family,
            'style' => 'normal',
            'weight' => 'normal',
        ], $regularUri);

        $registeredBold = $dompdf->getFontMetrics()->registerFont([
            'family' => $family,
            'style' => 'normal',
            'weight' => 'bold',
        ], $boldUri);

        if (! $registeredNormal || ! $registeredBold) {
            Log::warning('Birthday DomPDF font registration failed', [
                'family' => $family,
                'regular' => $regularUri,
                'bold' => $boldUri,
                'registered_normal' => $registeredNormal,
                'registered_bold' => $registeredBold,
            ]);
        }
    }

    private function dompdfFontDestination(string $fontDir, string $family, string $weight): string
    {
        $slug = str_replace(' ', '', ucwords(strtolower($family)));

        return $fontDir.'/'.$slug.'-'.ucfirst($weight).'.ttf';
    }

    private function isValidTrueTypeFont(string $path): bool
    {
        if (! File::exists($path) || File::size($path) < 1000) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $magic = fread($handle, 4);
        fclose($handle);

        return in_array($magic, ["\x00\x01\x00\x00", 'OTTO', 'true', 'typ1'], true);
    }

    private function storagePath(Donor $donor, string $extension): string
    {
        $directory = trim((string) config('donation.birthday.storage_directory', 'birthdays'), '/');
        $year = now()->year;

        return $directory.'/birthday-'.$donor->id.'-'.$year.'.'.$extension;
    }

    private function whatsappStoragePath(Donor $donor): string
    {
        $directory = trim((string) config('donation.birthday.storage_directory', 'birthdays'), '/');
        $year = now()->year;

        return $directory.'/birthday-'.$donor->id.'-'.$year.'-whatsapp.jpg';
    }

    private function deleteWhatsAppJpeg(Donor $donor): void
    {
        $path = $this->whatsappStoragePath($donor);

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function publicUrl(string $relativePath): string
    {
        $base = rtrim((string) config('donation.birthday.public_base_url', config('app.url')), '/');

        if (str_starts_with($base, 'http://')) {
            $base = 'https://'.substr($base, 7);
        }

        return $base.'/storage/'.ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    private function shouldRegenerate(string $templatePath, string $imagePath): bool
    {
        if (! File::exists($imagePath)) {
            return true;
        }

        return File::lastModified($templatePath) > File::lastModified($imagePath);
    }

    private function normalizeColor(string $color, string $default): string
    {
        $color = trim($color);

        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $color) === 1) {
            return '#'.ltrim($color, '#');
        }

        return $default;
    }

    private function convertPdfToPng(string $pdfPath, string $pngPath, int $width, int $height): bool
    {
        if (extension_loaded('imagick')) {
            try {
                $imagick = new \Imagick;
                $dpi = max(120, min(300, (int) config('donation.birthday.render_dpi', 220)));
                $imagick->setResolution($dpi, $dpi);
                $imagick->readImage($pdfPath.'[0]');
                $imagick->setImageBackgroundColor('white');
                $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
                $imagick->setImageFormat('png');
                $imagick->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
                $imagick->writeImage($pngPath);
                $imagick->clear();
                $imagick->destroy();

                return File::exists($pngPath);
            } catch (\Throwable $exception) {
                Log::warning('Imagick birthday conversion failed', ['error' => $exception->getMessage()]);
            }
        }

        $binary = (string) config('donation.birthday.pdftoppm_binary', '');

        if ($binary !== '' && File::exists($binary)) {
            $outputPrefix = preg_replace('/\.png$/i', '', $pngPath) ?: $pngPath;
            $dpi = (string) max(120, min(300, (int) config('donation.birthday.render_dpi', 220)));

            $result = Process::timeout(60)->run([
                $binary,
                '-png',
                '-singlefile',
                '-r',
                $dpi,
                $pdfPath,
                $outputPrefix,
            ]);

            if ($result->successful() && File::exists($pngPath)) {
                return true;
            }
        }

        return false;
    }

    private function createWhatsAppJpeg(string $pngRelativePath, string $jpegRelativePath): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }

        $pngPath = Storage::disk('public')->path($pngRelativePath);
        $jpegPath = Storage::disk('public')->path($jpegRelativePath);
        $source = @imagecreatefrompng($pngPath);

        if ($source === false) {
            return false;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $maxWidth = max(400, (int) config('donation.birthday.whatsapp_max_width', 1920));

        if ($width > $maxWidth) {
            $newWidth = $maxWidth;
            $newHeight = (int) round($height * ($maxWidth / $width));
            $resized = imagecreatetruecolor($newWidth, $newHeight);

            if ($resized === false) {
                imagedestroy($source);

                return false;
            }

            imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            imagedestroy($source);
            $source = $resized;
            $width = $newWidth;
            $height = $newHeight;
        }

        $canvas = imagecreatetruecolor($width, $height);

        if ($canvas === false) {
            imagedestroy($source);

            return false;
        }

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        $saved = imagejpeg(
            $canvas,
            $jpegPath,
            min(100, max(75, (int) config('donation.birthday.whatsapp_jpeg_quality', 94)))
        );
        imagedestroy($canvas);

        return $saved && File::exists($jpegPath);
    }
}

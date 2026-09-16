<?php

namespace App\Services;

use App\Models\Cause;
use App\Models\DonationOrder;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class DonationCertificateService
{
    public function generate(DonationOrder $order, bool $force = false): ?string
    {
        if (! config('donation.certificate.enabled')) {
            return null;
        }

        if (! Setting::isEnabled(Setting::SEND_DONATION_CERTIFICATE)) {
            return null;
        }

        $templatePath = $this->resolveTemplatePath($order);
        $fontPath = (string) config('donation.certificate.font');

        if (! File::exists($templatePath) || ! File::exists($fontPath)) {
            Log::warning('Donation certificate assets missing', [
                'order_id' => $order->id,
                'template_exists' => File::exists($templatePath),
                'font_exists' => File::exists($fontPath),
            ]);

            return null;
        }

        $donorName = $this->donorDisplayName($order);
        $dateValue = $this->formattedDateValue($order);

        $pngRelativePath = $this->storagePath($order, 'png');
        $pngAbsolutePath = Storage::disk('public')->path($pngRelativePath);

        if (! $force && Storage::disk('public')->exists($pngRelativePath) && ! $this->shouldRegenerate($templatePath, $pngAbsolutePath)) {
            return $this->publicUrl($pngRelativePath);
        }

        $imageSize = @getimagesize($templatePath);

        if ($imageSize === false) {
            Log::error('Donation certificate template dimensions could not be read', [
                'order_id' => $order->id,
                'template' => $templatePath,
            ]);

            return null;
        }

        [$pixelWidth, $pixelHeight] = $imageSize;
        $pageWidthPt = $pixelWidth * 72 / 96;
        $pageHeightPt = $pixelHeight * 72 / 96;

        $fontDir = storage_path('fonts');
        $fontCache = storage_path('fonts/cache');
        $fontDest = $fontDir.'/NotoSansGujarati-Regular.ttf';
        $fontBoldPath = (string) config('donation.certificate.font_bold');
        $fontBoldDest = $fontDir.'/NotoSansGujarati-Bold.ttf';

        File::ensureDirectoryExists($fontDir);
        File::ensureDirectoryExists($fontCache);

        if (! File::exists($fontDest)) {
            File::copy($fontPath, $fontDest);
        }

        if (File::exists($fontBoldPath) && ! File::exists($fontBoldDest)) {
            File::copy($fontBoldPath, $fontBoldDest);
        }

        $this->ensureDompdfGujaratiFont($fontDest, File::exists($fontBoldDest) ? $fontBoldDest : null);

        $nameConfig = (array) config('donation.certificate.name', []);
        $dateConfig = (array) config('donation.certificate.date', []);
        $nameFontWeight = $this->certificateNameFontWeight($nameConfig);
        $nameLayout = $this->certificateNameLayout($donorName, $pageWidthPt, $nameConfig);

        $pdf = Pdf::loadView('certificates.sanman-patra', [
            'donorName' => $donorName,
            'dateValue' => $dateValue,
            'dateLine' => $this->formattedDateLine($order),
            'templateImage' => 'data:image/jpeg;base64,'.base64_encode(File::get($templatePath)),
            'pageWidthPt' => $pageWidthPt,
            'pageHeightPt' => $pageHeightPt,
            'nameTopPercent' => (float) ($nameConfig['top_percent'] ?? 60.8),
            'nameSizePt' => $nameLayout['size_pt'],
            'nameMaxWidthPt' => $nameLayout['max_width_pt'],
            'nameWrap' => $nameLayout['wrap'],
            'nameColor' => $this->normalizeCertificateColor((string) ($nameConfig['color'] ?? ''), '#8B1538'),
            'nameFontWeight' => $nameFontWeight,
            'dateBottomPt' => (float) ($dateConfig['bottom_pt'] ?? 52),
            'dateBoxHeightPt' => (float) ($dateConfig['box_height_pt'] ?? 34),
            'dateSizePt' => (float) ($dateConfig['size_pt'] ?? 20),
            'dateColor' => $this->normalizeCertificateColor((string) ($dateConfig['color'] ?? ''), '#ffffff'),
        ])->setPaper([0, 0, $pageWidthPt, $pageHeightPt])->setOptions([
            'isRemoteEnabled' => true,
            'fontDir' => $fontDir,
            'fontCache' => $fontCache,
            'defaultFont' => 'noto sans gujarati',
            // Subsetting drops Gujarati glyphs (તારીખ → boxes); keep the full font embedded.
            'isFontSubsettingEnabled' => false,
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'defaultMediaType' => 'screen',
            'dpi' => 96,
        ]);

        Storage::disk('public')->makeDirectory((string) config('donation.certificate.storage_directory'));

        $tempPdfPath = tempnam(sys_get_temp_dir(), 'sanman-cert-');

        if ($tempPdfPath === false) {
            Log::error('Donation certificate temp PDF could not be created', [
                'order_id' => $order->id,
            ]);

            return null;
        }

        $tempPdfPath .= '.pdf';

        try {
            File::put($tempPdfPath, $pdf->output());

            if (! $this->convertPdfToPng($tempPdfPath, $pngAbsolutePath, $pixelWidth, $pixelHeight)) {
                if (File::exists($pngAbsolutePath)) {
                    File::delete($pngAbsolutePath);
                }

                Log::warning('Donation certificate PNG conversion unavailable', [
                    'order_id' => $order->id,
                    'hint' => 'Install the PHP imagick extension, Poppler pdftoppm, or Ghostscript to generate certificate images.',
                ]);

                return null;
            }

            $this->deleteStoredPdf($order);
            $this->deleteWhatsAppJpeg($order);

            return $this->publicUrl($pngRelativePath);
        } finally {
            if (File::exists($tempPdfPath)) {
                File::delete($tempPdfPath);
            }
        }
    }

    public function whatsappMediaUrl(DonationOrder $order, bool $force = false): ?string
    {
        if ($this->generate($order, $force) === null && ! Storage::disk('public')->exists($this->storagePath($order, 'png'))) {
            return null;
        }

        $pngRelativePath = $this->storagePath($order, 'png');
        $jpegRelativePath = $this->whatsappStoragePath($order);

        if ($force) {
            $this->deleteWhatsAppJpeg($order);
        }

        if (! Storage::disk('public')->exists($pngRelativePath)) {
            return null;
        }

        $maxBytes = max(500_000, (int) config('donation.certificate.whatsapp_max_bytes', 4_500_000));

        if ((bool) config('donation.certificate.whatsapp_prefer_png', true)
            && Storage::disk('public')->size($pngRelativePath) <= $maxBytes) {
            return $this->publicUrl($pngRelativePath);
        }

        if (! Storage::disk('public')->exists($jpegRelativePath)) {
            if (! $this->createWhatsAppJpeg($pngRelativePath, $jpegRelativePath)) {
                Log::warning('Using PNG for WhatsApp media because JPEG optimization failed', [
                    'order_id' => $order->id,
                ]);

                return $this->publicUrl($pngRelativePath);
            }
        }

        return $this->publicUrl($jpegRelativePath);
    }

    public function donorDisplayName(DonationOrder $order): string
    {
        $name = (string) ($order->donor_name ?? '');
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;
        $name = trim($name);

        if ($name === '') {
            return 'Donor';
        }

        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    public function formattedDateLine(DonationOrder $order): string
    {
        return $this->datePrefix().$this->formattedDateValue($order);
    }

    public function formattedDateValue(DonationOrder $order): string
    {
        $paidAt = $order->paid_at instanceof Carbon ? $order->paid_at : now();
        $format = (string) config('donation.certificate.date_format', 'd-m-Y');

        return $paidAt->format($format);
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function formattedDateParts(DonationOrder $order): array
    {
        return [$this->datePrefix(), $this->formattedDateValue($order)];
    }

    private function datePrefix(): string
    {
        return (string) config('donation.certificate.date_prefix', 'તારીખ : ');
    }

    private function resolveTemplatePath(DonationOrder $order): string
    {
        $order->loadMissing('items.causeModel');

        $cause = $order->items->first()?->causeModel;

        if ($cause instanceof Cause) {
            $causeTemplate = $this->absolutePathForStoredImage($cause->certificate_template);

            if ($causeTemplate !== null) {
                return $causeTemplate;
            }
        }

        return (string) config('donation.certificate.template');
    }

    private function absolutePathForStoredImage(?string $storedPath): ?string
    {
        if (! is_string($storedPath) || $storedPath === '') {
            return null;
        }

        $relativePath = str_starts_with($storedPath, 'storage/')
            ? substr($storedPath, strlen('storage/'))
            : ltrim($storedPath, '/');

        if ($relativePath === '') {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($relativePath);

        return File::exists($absolutePath) ? $absolutePath : null;
    }

    public function certificateNameFontWeight(?array $nameConfig = null): string
    {
        $nameConfig ??= (array) config('donation.certificate.name', []);

        $fontWeight = $nameConfig['font_weight'] ?? null;

        if (is_string($fontWeight) && trim($fontWeight) !== '') {
            return $this->normalizeCertificateFontWeight($fontWeight);
        }

        if (array_key_exists('bold', $nameConfig) && $nameConfig['bold'] !== null && $nameConfig['bold'] !== '') {
            return filter_var($nameConfig['bold'], FILTER_VALIDATE_BOOL) ? 'bold' : 'normal';
        }

        return 'normal';
    }

    private function normalizeCertificateFontWeight(string $weight): string
    {
        $weight = strtolower(trim($weight, " \t\n\r\0\x0B\"'"));

        if (in_array($weight, ['normal', 'bold', 'lighter'], true)) {
            return $weight;
        }

        if ($weight === 'bolder') {
            return 'bold';
        }

        if (is_numeric($weight)) {
            $numeric = (int) $weight;

            if ($numeric >= 600) {
                return 'bold';
            }

            if ($numeric <= 450) {
                return 'normal';
            }

            return (string) ((int) round($numeric / 100) * 100);
        }

        return 'normal';
    }

    /**
     * @return array{size_pt: float, max_width_pt: float, wrap: bool}
     */
    public function certificateNameLayout(string $donorName, float $pageWidthPt, ?array $nameConfig = null): array
    {
        $nameConfig ??= (array) config('donation.certificate.name', []);
        $baseSizePt = (float) ($nameConfig['size_pt'] ?? 44);
        $minSizePt = (float) ($nameConfig['min_size_pt'] ?? 32);
        $maxWidthPercent = (float) ($nameConfig['max_width_percent'] ?? 82);
        $maxWidthPt = $pageWidthPt * ($maxWidthPercent / 100);
        $charCount = max(1, mb_strlen(trim($donorName)));
        $widthFactor = $this->certificateNameFontWeight($nameConfig) === 'bold' ? 0.58 : 0.52;
        $estimatedWidth = $charCount * $baseSizePt * $widthFactor;
        $sizePt = $baseSizePt;

        if ($estimatedWidth > $maxWidthPt) {
            $sizePt = max($minSizePt, round($baseSizePt * ($maxWidthPt / $estimatedWidth), 1));
        }

        $wrap = $sizePt <= $minSizePt
            && ($charCount * $sizePt * $widthFactor) > $maxWidthPt;

        return [
            'size_pt' => $sizePt,
            'max_width_pt' => round($maxWidthPt, 1),
            'wrap' => $wrap,
        ];
    }

    private function normalizeCertificateColor(string $color, string $default): string
    {
        $color = trim($color, " \t\n\r\0\x0B\"'");

        if ($color === '') {
            return $default;
        }

        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $color) === 1) {
            return '#'.ltrim($color, '#');
        }

        return $color;
    }

    private function ensureDompdfGujaratiFont(string $fontPath, ?string $boldFontPath = null): void
    {
        $fontDir = dirname($fontPath);
        $fontCache = storage_path('fonts/cache');

        File::ensureDirectoryExists($fontDir);
        File::ensureDirectoryExists($fontCache);

        $installedFontsPath = $fontDir.'/installed-fonts.json';
        $familyKey = 'noto sans gujarati';
        $hasUfm = collect(glob($fontDir.'/*.ufm') ?: [])
            ->contains(fn (string $path): bool => str_contains(strtolower($path), 'noto')
                || str_contains(strtolower($path), 'gujarati'));

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
        // Absolute filesystem paths work on Windows and Linux. file:///D:/… fails DomPDF chroot checks on Windows.
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
            Log::warning('Certificate DomPDF Gujarati font registration failed', [
                'regular' => $fontUri,
                'bold' => $boldFontUri,
                'registered_normal' => $registeredNormal,
                'registered_bold' => $registeredBold,
            ]);
        }
    }

    private function deleteStoredPdf(DonationOrder $order): void
    {
        $pdfRelativePath = $this->storagePath($order, 'pdf');

        if (Storage::disk('public')->exists($pdfRelativePath)) {
            Storage::disk('public')->delete($pdfRelativePath);
        }
    }

    private function storagePath(DonationOrder $order, string $extension): string
    {
        $directory = trim((string) config('donation.certificate.storage_directory', 'certificates'), '/');

        return $directory.'/sanman-'.$order->id.'.'.$extension;
    }

    private function publicUrl(string $relativePath): string
    {
        $base = rtrim((string) config('donation.certificate.public_base_url', config('app.url')), '/');

        if (str_starts_with($base, 'http://')) {
            $base = 'https://'.substr($base, 7);
        }

        return $base.'/storage/'.ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    private function whatsappStoragePath(DonationOrder $order): string
    {
        $directory = trim((string) config('donation.certificate.storage_directory', 'certificates'), '/');

        return $directory.'/sanman-'.$order->id.'-whatsapp.jpg';
    }

    private function deleteWhatsAppJpeg(DonationOrder $order): void
    {
        $jpegRelativePath = $this->whatsappStoragePath($order);

        if (Storage::disk('public')->exists($jpegRelativePath)) {
            Storage::disk('public')->delete($jpegRelativePath);
        }
    }

    private function createWhatsAppJpeg(string $pngRelativePath, string $jpegRelativePath): bool
    {
        if (! extension_loaded('gd')) {
            return false;
        }

        $pngPath = Storage::disk('public')->path($pngRelativePath);
        $jpegPath = Storage::disk('public')->path($jpegRelativePath);

        if (! File::exists($pngPath)) {
            return false;
        }

        $source = @imagecreatefrompng($pngPath);

        if ($source === false) {
            return false;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $maxWidth = max(400, (int) config('donation.certificate.whatsapp_max_width', 1200));

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

        $quality = min(100, max(75, (int) config('donation.certificate.whatsapp_jpeg_quality', 94)));
        $maxBytes = max(500_000, (int) config('donation.certificate.whatsapp_max_bytes', 4_500_000));
        $saved = false;

        while ($quality >= 75) {
            $saved = imagejpeg($canvas, $jpegPath, $quality);

            if (! $saved || ! File::exists($jpegPath)) {
                break;
            }

            if (File::size($jpegPath) <= $maxBytes) {
                break;
            }

            $quality -= 3;
        }

        imagedestroy($canvas);

        return $saved && File::exists($jpegPath);
    }

    private function renderDpi(): int
    {
        return max(120, min(300, (int) config('donation.certificate.render_dpi', 220)));
    }

    private function shouldRegenerate(string $templatePath, string $certificatePath): bool
    {
        if (! File::exists($certificatePath)) {
            return true;
        }

        return File::lastModified($templatePath) > File::lastModified($certificatePath);
    }

    private function convertPdfToPng(string $pdfPath, string $pngPath, int $width, int $height): bool
    {
        if ($this->convertPdfToPngWithImagick($pdfPath, $pngPath, $width, $height)) {
            return true;
        }

        if ($this->convertPdfToPngWithPoppler($pdfPath, $pngPath)) {
            return true;
        }

        return $this->convertPdfToPngWithGhostscript($pdfPath, $pngPath, $width, $height);
    }

    private function convertPdfToPngWithImagick(string $pdfPath, string $pngPath, int $width, int $height): bool
    {
        if (! extension_loaded('imagick')) {
            return false;
        }

        try {
            $imagick = new \Imagick;
            $dpi = $this->renderDpi();
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
            Log::warning('Imagick certificate conversion failed', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function convertPdfToPngWithPoppler(string $pdfPath, string $pngPath): bool
    {
        $binary = (string) config('donation.certificate.pdftoppm_binary', '');

        if ($binary === '' || ! File::exists($binary)) {
            return false;
        }

        $outputPrefix = preg_replace('/\.png$/i', '', $pngPath) ?: $pngPath;

        try {
            $dpi = (string) $this->renderDpi();

            $result = Process::timeout(60)->run([
                $binary,
                '-png',
                '-singlefile',
                '-r',
                $dpi,
                $pdfPath,
                $outputPrefix,
            ]);

            if (! $result->successful()) {
                Log::warning('Poppler certificate conversion failed', [
                    'error' => trim($result->errorOutput()),
                ]);

                return false;
            }

            return File::exists($pngPath);
        } catch (\Throwable $exception) {
            Log::warning('Poppler certificate conversion failed', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function convertPdfToPngWithGhostscript(string $pdfPath, string $pngPath, int $width, int $height): bool
    {
        $binary = $this->ghostscriptBinary();

        if ($binary === null) {
            return false;
        }

        try {
            $dpi = (string) $this->renderDpi();

            $result = Process::timeout(60)->run([
                $binary,
                '-dNOPAUSE',
                '-dBATCH',
                '-dSAFER',
                '-sDEVICE=pngalpha',
                '-r'.$dpi,
                '-g'.$width.'x'.$height,
                '-sOutputFile='.$pngPath,
                $pdfPath,
            ]);

            if (! $result->successful()) {
                Log::warning('Ghostscript certificate conversion failed', [
                    'error' => trim($result->errorOutput()),
                ]);

                return false;
            }

            return File::exists($pngPath);
        } catch (\Throwable $exception) {
            Log::warning('Ghostscript certificate conversion failed', [
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function ghostscriptBinary(): ?string
    {
        $candidates = [
            'gswin64c',
            'gswin32c',
            'gs',
        ];

        $programFiles = getenv('ProgramFiles') ?: 'C:\\Program Files';
        $programFilesX86 = getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)';

        foreach ([$programFiles, $programFilesX86] as $root) {
            $gsRoot = $root.'\\gs';

            if (! is_dir($gsRoot)) {
                continue;
            }

            $versions = glob($gsRoot.'\\gs*\\bin\\gswin64c.exe') ?: [];

            foreach ($versions as $path) {
                $candidates[] = $path;
            }
        }

        foreach ($candidates as $candidate) {
            try {
                $result = Process::timeout(10)->run([$candidate, '--version']);

                if ($result->successful()) {
                    return $candidate;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}

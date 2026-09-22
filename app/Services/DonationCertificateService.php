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
        $nameTopPercent = $this->certificateNameTopPercent($order, $nameConfig);
        $latinDonorName = $this->donorNameUsesLatinScript($donorName);
        $nameFontFamily = $latinDonorName
            ? 'DejaVu Sans, sans-serif'
            : '"noto sans gujarati", "Noto Sans Gujarati", DejaVu Sans, sans-serif';

        $pdf = Pdf::loadView('certificates.sanman-patra', [
            'donorName' => $donorName,
            'dateValue' => $dateValue,
            'dateLine' => $this->formattedDateLine($order),
            // Text-only overlay: never send the artwork through DomPDF (it shifts CMYK/JPEG colors).
            'templateImage' => null,
            'pageWidthPt' => $pageWidthPt,
            'pageHeightPt' => $pageHeightPt,
            'nameTopPercent' => $nameTopPercent,
            'nameSizePt' => $nameLayout['size_pt'],
            'nameMaxWidthPt' => $nameLayout['max_width_pt'],
            'nameWrap' => $nameLayout['wrap'],
            'nameColor' => $this->normalizeCertificateColor((string) ($nameConfig['color'] ?? ''), '#2d3253'),
            'nameFontWeight' => $nameFontWeight,
            'nameFontFamily' => $nameFontFamily,
            'dateBottomPt' => (float) ($dateConfig['bottom_pt'] ?? 30),
            'dateBoxHeightPt' => (float) ($dateConfig['box_height_pt'] ?? 22),
            'dateSizePt' => (float) ($dateConfig['size_pt'] ?? 12),
            'dateColor' => $this->normalizeCertificateColor((string) ($dateConfig['color'] ?? ''), '#0B1F6B'),
            'dateAlign' => $this->normalizeCertificateDateAlign((string) ($dateConfig['align'] ?? 'left')),
            'dateLeftPercent' => (float) ($dateConfig['left_percent'] ?? 17),
            'dateWidthPercent' => (float) ($dateConfig['width_percent'] ?? 22),
            'datePaddingLeftPt' => (float) ($dateConfig['padding_left_pt'] ?? 0),
            'datePaddingRightPt' => (float) ($dateConfig['padding_right_pt'] ?? 0),
        ])->setPaper([0, 0, $pageWidthPt, $pageHeightPt])->setOptions([
            'isRemoteEnabled' => true,
            'fontDir' => $fontDir,
            'fontCache' => $fontCache,
            'defaultFont' => $latinDonorName ? 'dejavu sans' : 'noto sans gujarati',
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
        $tempOverlayPath = sys_get_temp_dir().'/sanman-overlay-'.$order->id.'-'.uniqid('', true).'.png';

        try {
            File::put($tempPdfPath, $pdf->output());

            if (! $this->convertPdfToPng($tempPdfPath, $tempOverlayPath, $pixelWidth, $pixelHeight)) {
                Log::warning('Donation certificate PNG conversion unavailable', [
                    'order_id' => $order->id,
                    'hint' => 'Install the PHP imagick extension, Poppler pdftoppm, or Ghostscript to generate certificate images.',
                ]);

                return null;
            }

            if (! $this->compositeNameOntoTemplate($templatePath, $tempOverlayPath, $pngAbsolutePath)) {
                Log::warning('Donation certificate template composite failed', [
                    'order_id' => $order->id,
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

            if (File::exists($tempOverlayPath)) {
                File::delete($tempOverlayPath);
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

        // Keep form script: English stays English, Gujarati stays Gujarati.
        if ($this->donorNameUsesLatinScript($name)) {
            return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
        }

        return $name;
    }

    /**
     * True when the form name is Latin/English letters (no Gujarati/Indic script).
     */
    public function donorNameUsesLatinScript(string $name): bool
    {
        $name = trim($name);

        if ($name === '') {
            return true;
        }

        return preg_match('/^[\p{Latin}0-9\s\.\'\-\(\)&,+\/]+$/u', $name) === 1;
    }

    public function formattedDateLine(DonationOrder $order): string
    {
        return $this->datePrefix($order).$this->formattedDateValue($order);
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
        return [$this->datePrefix($order), $this->formattedDateValue($order)];
    }

    /**
     * Gujarat donors get the Gujarati certificate; everyone else (including
     * missing/empty state) gets the English certificate.
     */
    public function usesGujaratiCertificate(DonationOrder $order): bool
    {
        $state = preg_replace('/\s+/u', ' ', trim((string) ($order->state ?? ''))) ?? '';

        return $state !== '' && mb_strtolower($state, 'UTF-8') === 'gujarat';
    }

    public function certificateLocale(DonationOrder $order): string
    {
        return $this->usesGujaratiCertificate($order) ? 'gu' : 'en';
    }

    /**
     * English artwork gold underline sits higher (~63%) than Gujarati (~66%).
     *
     * @param  array<string, mixed>|null  $nameConfig
     */
    public function certificateNameTopPercent(DonationOrder $order, ?array $nameConfig = null): float
    {
        $nameConfig ??= (array) config('donation.certificate.name', []);

        if ($this->usesGujaratiCertificate($order)) {
            return (float) ($nameConfig['top_percent'] ?? 61.5);
        }

        return (float) ($nameConfig['top_percent_english'] ?? 61.5);
    }

    private function datePrefix(DonationOrder $order): string
    {
        if ($this->usesGujaratiCertificate($order)) {
            return (string) config('donation.certificate.date_prefix', 'તારીખ : ');
        }

        return (string) config('donation.certificate.date_prefix_english', 'Date : ');
    }

    private function resolveTemplatePath(DonationOrder $order): string
    {
        $gujaratiTemplate = (string) config('donation.certificate.template');
        $order->loadMissing('items.causeModel');
        $cause = $order->items->first()?->causeModel;

        if ($this->usesGujaratiCertificate($order)) {
            if ($cause instanceof Cause) {
                $causeTemplate = $this->absolutePathForStoredImage($cause->certificate_template);

                if ($causeTemplate !== null) {
                    return $causeTemplate;
                }
            }

            return $gujaratiTemplate;
        }

        if ($cause instanceof Cause) {
            $englishCauseTemplate = $this->absolutePathForStoredImage($cause->certificate_template_english);

            if ($englishCauseTemplate !== null) {
                return $englishCauseTemplate;
            }
        }

        $englishTemplate = (string) config('donation.certificate.template_english');

        if ($englishTemplate !== '' && File::exists($englishTemplate)) {
            return $englishTemplate;
        }

        // English artwork not uploaded yet — reuse the shared background image
        // but still render English date text via datePrefix().
        return $gujaratiTemplate;
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

    private function normalizeCertificateDateAlign(string $align): string
    {
        $align = strtolower(trim($align));

        return in_array($align, ['left', 'center', 'right'], true) ? $align : 'left';
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
        $locale = $this->certificateLocale($order);

        return $directory.'/sanman-'.$order->id.'-'.$locale.'.'.$extension;
    }

    private function publicUrl(string $relativePath): string
    {
        $base = rtrim((string) config('donation.certificate.public_base_url', config('app.url')), '/');

        if (str_starts_with($base, 'http://')) {
            $base = 'https://'.substr($base, 7);
        }

        $url = $base.'/storage/'.ltrim(str_replace('\\', '/', $relativePath), '/');
        $absolutePath = Storage::disk('public')->path($relativePath);

        // Nginx caches /storage for years; bust so regenerated certificates show immediately.
        if (is_file($absolutePath)) {
            $url .= '?v='.filemtime($absolutePath);
        }

        return $url;
    }

    private function whatsappStoragePath(DonationOrder $order): string
    {
        $directory = trim((string) config('donation.certificate.storage_directory', 'certificates'), '/');
        $locale = $this->certificateLocale($order);

        return $directory.'/sanman-'.$order->id.'-'.$locale.'-whatsapp.jpg';
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

    /**
     * Convert print CMYK templates to sRGB once, keeping the embedded ICC profile
     * during transform so greens/golds match the source artwork.
     */
    private function preparedSrgbTemplatePath(string $templatePath): ?string
    {
        if (! extension_loaded('imagick') || ! File::exists($templatePath)) {
            return null;
        }

        $cacheDir = storage_path('app/certificate-template-cache');
        File::ensureDirectoryExists($cacheDir);

        $cachePath = $cacheDir.'/'.hash(
            'sha256',
            $templatePath.'|'.File::lastModified($templatePath).'|'.File::size($templatePath).'|srgb-v2'
        ).'.png';

        if (File::exists($cachePath)) {
            return $cachePath;
        }

        try {
            $imagick = new \Imagick($templatePath);

            if ($imagick->getImageColorspace() === \Imagick::COLORSPACE_CMYK
                || count($imagick->getImageProfiles('icc', false)) > 0) {
                $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            }

            $imagick->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            $imagick->setImageDepth(8);
            $imagick->setImageFormat('png');
            $imagick->stripImage();
            $imagick->writeImage($cachePath);
            $imagick->clear();
            $imagick->destroy();

            return File::exists($cachePath) ? $cachePath : null;
        } catch (\Throwable $exception) {
            Log::warning('Certificate template sRGB conversion failed', [
                'template' => $templatePath,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Keep artwork pixels identical to the source template. DomPDF only renders
     * the donor name on white; near-white pixels are discarded and the rest is
     * composited onto the original template.
     */
    private function compositeNameOntoTemplate(string $templatePath, string $overlayPngPath, string $outputPngPath): bool
    {
        if (! extension_loaded('imagick') || ! File::exists($overlayPngPath)) {
            return false;
        }

        $basePath = $this->preparedSrgbTemplatePath($templatePath) ?? $templatePath;

        if (! File::exists($basePath)) {
            return false;
        }

        try {
            $base = new \Imagick($basePath);
            $base->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            $base->setImageDepth(8);

            $overlay = new \Imagick($overlayPngPath);
            $overlay->setImageColorspace(\Imagick::COLORSPACE_SRGB);
            $overlay->setImageDepth(8);

            $width = $base->getImageWidth();
            $height = $base->getImageHeight();

            if ($overlay->getImageWidth() !== $width || $overlay->getImageHeight() !== $height) {
                $overlay->resizeImage($width, $height, \Imagick::FILTER_LANCZOS, 1);
            }

            // Knock out the white DomPDF page so only name ink remains.
            $overlay->transparentPaintImage(
                new \ImagickPixel('white'),
                0.0,
                0.12 * \Imagick::getQuantum(),
                false,
            );

            $base->compositeImage($overlay, \Imagick::COMPOSITE_OVER, 0, 0);
            $base->setImageFormat('png');
            $base->setImageDepth(8);
            $base->writeImage($outputPngPath);

            $overlay->clear();
            $overlay->destroy();
            $base->clear();
            $base->destroy();

            return File::exists($outputPngPath);
        } catch (\Throwable $exception) {
            Log::warning('Certificate name composite failed', [
                'template' => $templatePath,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
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
            $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            $imagick->setImageDepth(8);
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

<?php

namespace App\Services;

use App\Helpers\NumberHelper;
use App\Models\DonationOrder;
use App\Support\ReceiptAssets;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DonationReceiptPdfService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function make(string $view, array $data = []): DomPdf
    {
        $fontDir = storage_path('fonts');
        $fontCache = storage_path('fonts/cache');

        File::ensureDirectoryExists($fontDir);
        File::ensureDirectoryExists($fontCache);

        $fontSource = public_path('fonts/NotoSansGujarati-Regular.ttf');
        $fontDest = $fontDir.'/NotoSansGujarati-Regular.ttf';

        if (File::exists($fontSource) && ! File::exists($fontDest)) {
            File::copy($fontSource, $fontDest);
        }

        $fontBase64 = File::exists($fontDest) ? base64_encode(File::get($fontDest)) : '';

        $signaturePath = public_path('images/signature.jpeg');
        $signatureData = File::exists($signaturePath)
            ? 'data:image/jpeg;base64,'.base64_encode(File::get($signaturePath))
            : '';

        return Pdf::loadView($view, array_merge($data, [
            'isPdf' => true,
            'pdfFontBase64' => $fontBase64,
            'pdfPoppinsFonts' => $this->poppinsFontDataUris(),
            'pdfSignatureData' => $signatureData,
            'pdfImages' => $this->fetchReceiptImagesAsDataUris(),
        ]))->setOptions([
            'isRemoteEnabled' => false,
            'fontDir' => $fontDir,
            'fontCache' => $fontCache,
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'defaultMediaType' => 'print',
            'dpi' => 96,
        ]);
    }

    public function whatsappMediaUrl(DonationOrder $order, bool $force = false): ?string
    {
        if (! $order->isPaid()) {
            return null;
        }

        $relativePath = $this->storagePath($order);

        if (! $force && Storage::disk('public')->exists($relativePath)) {
            return $this->publicUrl($relativePath);
        }

        $pdfContent = $this->generatePdfOutput($order);
        if ($pdfContent === null) {
            return null;
        }

        Storage::disk('public')->put($relativePath, $pdfContent);

        return $this->publicUrl($relativePath);
    }

    public function whatsappFilename(DonationOrder $order): string
    {
        $receiptNumber = $order->receipt_number ?: (string) $order->id;

        return 'donation-receipt-'.$receiptNumber.'.pdf';
    }

    private function generatePdfOutput(DonationOrder $order): ?string
    {
        try {
            $order->loadMissing(['items.causeModel', 'items.package', 'donor']);
            $amountInWords = NumberHelper::amountInWords((float) $order->total_amount);
            $view = (string) config('receipt.view', 'receipts.donation-minimal');

            return $this->make($view, [
                'order' => $order,
                'amountInWords' => $amountInWords,
            ])->output();
        } catch (\Throwable $e) {
            Log::error('Donation receipt PDF generation failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function storagePath(DonationOrder $order): string
    {
        $directory = trim((string) config('receipt.storage_directory', 'receipts'), '/');

        return $directory.'/donation-receipt-'.$order->id.'.pdf';
    }

    private function publicUrl(string $relativePath): string
    {
        $base = rtrim((string) config('receipt.public_base_url', config('app.url')), '/');

        if (str_starts_with($base, 'http://')) {
            $base = 'https://'.substr($base, 7);
        }

        return $base.'/storage/'.ltrim(str_replace('\\', '/', $relativePath), '/');
    }

    /**
     * @return array<string, string>
     */
    private function poppinsFontDataUris(): array
    {
        $fonts = [
            'regular' => public_path('fonts/Poppins-Regular.ttf'),
            'semibold' => public_path('fonts/Poppins-SemiBold.ttf'),
            'bold' => public_path('fonts/Poppins-Bold.ttf'),
        ];

        $result = [];

        foreach ($fonts as $weight => $path) {
            if (File::exists($path)) {
                $result[$weight] = 'data:font/truetype;base64,'.base64_encode(File::get($path));
            }
        }

        return $result;
    }

    /**
     * Fetch receipt images and inline them as base64 data URIs for dompdf.
     *
     * @return array<string, string>
     */
    private function fetchReceiptImagesAsDataUris(): array
    {
        $result = [];

        foreach (ReceiptAssets::all() as $key => $url) {
            if ($url === '') {
                continue;
            }

            $localPath = $this->localReceiptImagePath($key);
            if ($localPath !== null && File::exists($localPath)) {
                $result[$key] = $this->fileToDataUri($localPath);

                continue;
            }

            try {
                /** @var \Illuminate\Http\Client\Response $response */
                $response = Http::timeout(15)->get($url);

                if ($response->successful()) {
                    $mime = strtolower(explode(';', $response->header('Content-Type') ?: 'image/jpeg')[0]);
                    $result[$key] = 'data:'.$mime.';base64,'.base64_encode($response->body());
                }
            } catch (\Exception $e) {
                Log::warning('DonationReceiptPdfService: could not fetch receipt image', [
                    'key' => $key,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    private function localReceiptImagePath(string $key): ?string
    {
        $path = config("receipt.images.{$key}");

        if (! is_string($path) || $path === '' || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
        }

        return public_path(ltrim($path, '/'));
    }

    private function fileToDataUri(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode(File::get($path));
    }
}

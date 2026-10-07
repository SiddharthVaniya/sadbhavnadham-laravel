<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Generates a composite JPEG image: the qrbg.jpg background with a QR
 * code for the given URL composited on top.
 *
 * The image is persisted to public storage so AiSensy can fetch it via
 * a stable, publicly-accessible URL.
 */
class PaymentLinkQrService
{
    private const QR_SIZE = 350;

    /**
     * Generate the composite QR image for the given URL and return its
     * public URL (suitable for passing to AiSensy as media.url).
     *
     * Returns null on failure so callers can decide whether to skip sending.
     */
    public function publicUrlForLink(string $paymentLinkUrl, string $orderKey): ?string
    {
        $path = "payment-qr/{$orderKey}.jpg";

        // Re-use cached image if it already exists (idempotent for re-sends).
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        $imageData = $this->buildCompositeImage($paymentLinkUrl);
        if ($imageData === null) {
            return null;
        }

        Storage::disk('public')->put($path, $imageData);

        return Storage::disk('public')->url($path);
    }

    /**
     * Build the raw JPEG bytes and return them (for HTTP response use).
     */
    public function buildCompositeImageResponse(string $paymentLinkUrl): ?string
    {
        return $this->buildCompositeImage($paymentLinkUrl);
    }

    // -------------------------------------------------------------------------

    private function buildCompositeImage(string $paymentLinkUrl): ?string
    {
        $bgPath = public_path('images/qrbg.jpg');
        if (! file_exists($bgPath)) {
            Log::error('PaymentLinkQrService: background image not found', ['path' => $bgPath]);

            return null;
        }

        $qrData = $this->fetchQrBytes($paymentLinkUrl);
        if ($qrData === null) {
            return null;
        }

        $bg = imagecreatefromjpeg($bgPath);
        if (! $bg) {
            Log::error('PaymentLinkQrService: could not load background JPEG');

            return null;
        }

        $qrImage = imagecreatefromstring($qrData);
        if (! $qrImage) {
            imagedestroy($bg);
            Log::error('PaymentLinkQrService: could not create QR image from string');

            return null;
        }

        $bgWidth  = imagesx($bg);
        $bgHeight = imagesy($bg);
        $qrWidth  = imagesx($qrImage);
        $qrHeight = imagesy($qrImage);

        // Horizontally centred; shifted slightly below vertical centre.
        $dstX = (int) (($bgWidth - $qrWidth) / 2);
        $dstY = (int) (($bgHeight - $qrHeight) / 2) + 50;

        imagecopy($bg, $qrImage, $dstX, $dstY, 0, 0, $qrWidth, $qrHeight);

        ob_start();
        imagejpeg($bg, null, 90);
        $bytes = ob_get_clean();

        imagedestroy($bg);
        imagedestroy($qrImage);

        return $bytes ?: null;
    }

    private function fetchQrBytes(string $url): ?string
    {
        $size  = self::QR_SIZE;
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=".urlencode($url);

        try {
            $response = Http::timeout(10)->get($qrUrl);
            if ($response->successful()) {
                return $response->body();
            }

            Log::warning('PaymentLinkQrService: QR API returned non-success', [
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::error('PaymentLinkQrService: QR API request failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }
}

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

    /**
     * Generate (and cache) a square Direct Pay QR PNG for a marketer and return
     * its public URL.
     *
     * Razorpay's `image_url` is a tall branded "Scan & Pay" card that wraps the
     * real `upi://pay` intent. We decode that intent and re-encode it as a plain
     * square QR so the marketer QR visually matches the site's default footer QR
     * (which is itself a `upi://pay` intent). When the intent cannot be decoded,
     * we fall back to encoding the Razorpay link directly.
     *
     * Returns null on failure so callers can keep the default QR.
     */
    public function squareQrPublicUrl(string $linkUrl, string $cacheKey): ?string
    {
        $safeKey = preg_replace('/[^a-z0-9._-]+/i', '-', $cacheKey) ?? '';
        $safeKey = trim($safeKey, '-.');

        if ($safeKey === '') {
            $safeKey = substr(md5($linkUrl), 0, 16);
        }

        // Content-addressed filename: changing a marketer's QR link yields a new
        // path, which also busts any Next.js/CDN image cache keyed on the URL.
        $hash = substr(md5($linkUrl), 0, 8);
        $path = "partner-qr/{$safeKey}-{$hash}.png";

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        $payload = $this->directPayIntentForLink($linkUrl) ?? $linkUrl;

        $imageData = $this->fetchQrBytes($payload);
        if ($imageData === null) {
            return null;
        }

        Storage::disk('public')->put($path, $imageData);

        $this->pruneStaleQrImages($safeKey, $path);

        return Storage::disk('public')->url($path);
    }

    /** Remove older content-addressed variants for the same marketer key. */
    private function pruneStaleQrImages(string $safeKey, string $keep): void
    {
        $pattern = '#^partner-qr/'.preg_quote($safeKey, '#').'-[0-9a-f]{8}\.png$#';

        foreach (Storage::disk('public')->files('partner-qr') as $file) {
            if ($file !== $keep && preg_match($pattern, $file) === 1) {
                Storage::disk('public')->delete($file);
            }
        }
    }

    /**
     * Follow the Razorpay QR link to its branded card and decode the embedded
     * `upi://pay` intent so we can re-encode it as a square Direct Pay QR.
     */
    private function directPayIntentForLink(string $linkUrl): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->get($linkUrl);

            if (! $response->successful()) {
                return null;
            }

            $intent = $this->decodeQrIntent($response->body());

            if ($intent === null || ! str_starts_with($intent, 'upi://pay')) {
                return null;
            }

            return $intent;
        } catch (\Throwable $e) {
            Log::warning('PaymentLinkQrService: could not decode Razorpay UPI intent', [
                'url' => $linkUrl,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /** Decode a QR image (binary PNG/JPEG) to its text payload via zbarimg. */
    private function decodeQrIntent(string $imageBytes): ?string
    {
        if ($imageBytes === '') {
            return null;
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open('zbarimg --quiet --raw - 2>/dev/null', $descriptors, $pipes);

        if (! is_resource($process)) {
            return null;
        }

        fwrite($pipes[0], $imageBytes);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            return null;
        }

        $text = trim((string) $output);

        return $text !== '' ? $text : null;
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

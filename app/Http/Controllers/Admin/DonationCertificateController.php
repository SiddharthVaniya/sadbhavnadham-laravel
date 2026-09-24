<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonationOrder;
use App\Services\DonationCertificateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class DonationCertificateController extends Controller
{
    public function __construct(
        private DonationCertificateService $certificates,
    ) {}

    public function show(DonationOrder $order): RedirectResponse
    {
        $this->authorize('view', $order);
        abort_if(! $order->isPaid(), 403, 'Only paid donations have certificates.');

        $url = $this->certificates->existingPublicUrl($order)
            ?? $this->certificates->whatsappMediaUrl($order);

        abort_if($url === null, 404, 'Certificate is not available for this donation.');

        return redirect()->away($url);
    }

    public function regenerate(DonationOrder $order): JsonResponse|RedirectResponse
    {
        $this->authorize('view', $order);

        if (! $order->isPaid()) {
            return $this->failure($order, 'Only paid donations can have a certificate.', 422);
        }

        $url = $this->certificates->whatsappMediaUrl($order, true);

        if ($url === null) {
            return $this->failure(
                $order,
                'Could not generate the certificate. Check certificate templates and settings.',
                500,
            );
        }

        if (request()->expectsJson() || request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'ok' => true,
                'url' => $url,
                'message' => 'Certificate regenerated.',
            ]);
        }

        return redirect()
            ->route('admin.donations.show', $order)
            ->with('status', 'Certificate regenerated.');
    }

    private function failure(DonationOrder $order, string $message, int $status): JsonResponse|RedirectResponse
    {
        if (request()->expectsJson() || request()->wantsJson() || request()->ajax()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], $status);
        }

        return redirect()
            ->route('admin.donations.show', $order)
            ->with('status', $message)
            ->with('flash_tone', 'warning');
    }
}

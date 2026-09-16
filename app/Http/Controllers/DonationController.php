<?php

namespace App\Http\Controllers;

use App\Helpers\DonationRequestLogContext;
use App\Http\Requests\CheckPanRequirementRequest;
use App\Http\Requests\StoreDonationRequest;
use App\Http\Requests\StoreRecurringDonationRequest;
use App\Models\Cause;
use App\Models\DonationItem;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Models\Donor;
use App\Services\AnalyticsService;
use App\Services\LinkTrackingService;
use App\Services\RazorpaySubscriptionService;
use App\Support\CampaignDonationContext;
use App\Support\DonationThankYouUrl;
use App\Support\PanRequirementService;
use App\Support\RazorpayDonationLabels;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Razorpay\Api\Api;

class DonationController extends Controller
{
    public function __construct(
        private AnalyticsService $analytics,
        private PanRequirementService $panRequirement,
        private LinkTrackingService $linkTracking,
    ) {}

    public function panRequirement(CheckPanRequirementRequest $request): JsonResponse
    {
        $cause = Cause::query()
            ->where('slug', $request->input('cause'))
            ->with('packages')
            ->firstOrFail();

        $currentAmount = $this->panRequirement->resolveDonationTotalFromRequest($request, $cause);
        $email = (string) $request->input('donor_email', '');
        $phone = (string) $request->input('donor_phone', '');

        if (! $cause->pan_required) {
            return response()->json([
                'required' => false,
                'current_amount' => round($currentAmount, 2),
                'fy_paid_total' => 0,
                'combined_total' => round($currentAmount, 2),
                'threshold' => $this->panRequirement->threshold(),
                'known_pan_number' => null,
            ]);
        }

        return response()->json(
            $this->panRequirement->evaluate($cause, $currentAmount, $email, $phone)
        );
    }

    /**
     * 🇮🇳 INDIAN DONATION (RAZORPAY)
     * Triggered ONLY by "Donate from India" button
     */
    public function razorpay(StoreDonationRequest $request): JsonResponse
    {
        Log::info('Donation request received', DonationRequestLogContext::fromInput($request->validated()));

        $donation = DB::transaction(function () use ($request): array {
            $cause = Cause::query()
                ->where('slug', $request->input('cause'))
                ->with('packages')
                ->firstOrFail();

            $campaignContext = CampaignDonationContext::fromRequest($request);

            $package = null;
            if ($request->filled('package_id')) {
                $package = $cause->packages->firstWhere('id', (int) $request->input('package_id'));
            }

            $quantity = max(1, (int) $request->input('quantity', 1));
            $unitAmount = $package?->amount ?? (float) $request->input('amount');
            $totalAmount = $unitAmount * $quantity;
            $title = RazorpayDonationLabels::itemTitle($cause, $package, $request->input('title'));
            if ($title === 'Custom Donation' && $campaignContext) {
                $title = $campaignContext['name'];
            }
            $meta = $package?->meta ?? [];
            $dailyNeedsSummary = trim((string) (
                $request->input('daily_needs_summary')
                ?: ($cause->slug === Cause::SLUG_DAILY_NEEDS ? $request->input('title') : '')
            ));
            $dailyNeedsLines = $cause->slug === Cause::SLUG_DAILY_NEEDS
                ? collect($request->input('daily_needs_lines', []))
                    ->filter(fn ($line) => is_array($line) && filled($line['title'] ?? null))
                    ->map(fn (array $line) => [
                        'id' => (string) ($line['id'] ?? ''),
                        'title' => Str::limit(trim((string) $line['title']), 255, ''),
                        'qty' => max(1, (int) ($line['qty'] ?? 1)),
                        'unit' => Str::limit((string) ($line['unit'] ?? 'kg'), 32, ''),
                        'qty_label' => Str::limit((string) ($line['qty_label'] ?? ($line['qty'] ?? '1')), 64, ''),
                        'unit_price' => (float) ($line['unit_price'] ?? 0),
                        'amount' => (float) ($line['amount'] ?? 0),
                    ])
                    ->values()
                    ->all()
                : [];

            $donorSnapshot = $this->donorSnapshotFromRequest($request);

            $donor = Donor::resolveFromDonationSnapshot($donorSnapshot);

            /** Create Donation Order (PENDING) */
            $order = DonationOrder::create([
                'order_uuid' => (string) Str::uuid(),
                'payment_provider' => DonationOrder::PROVIDER_RAZORPAY,
                'donor_id' => $donor->id,
                'donor_name' => $donorSnapshot['donor_name'],
                'donor_email' => $donorSnapshot['donor_email'],
                'donor_phone' => $donorSnapshot['donor_phone'],
                'date_of_birth' => $donorSnapshot['date_of_birth'],
                'pan_number' => $donorSnapshot['pan_number'],
                'address' => $donorSnapshot['address'],
                'pincode' => $donorSnapshot['pincode'],
                'city' => $donorSnapshot['city'],
                'state' => $donorSnapshot['state'],
                'country' => $donorSnapshot['country'],
                'donor_country_code' => $donorSnapshot['donor_country_code'],
                'consent_indian_citizen' => $donorSnapshot['consent_indian_citizen'],
                'currency' => 'INR',
                'total_amount' => $totalAmount,
                'status' => DonationOrder::STATUS_PENDING,
            ]);

            /** Donation Item */
            DonationItem::create([
                'donation_order_id' => $order->id,
                'cause_id' => $cause->id,
                'cause_package_id' => $package?->id,
                'donation_campaign_id' => $campaignContext['id'] ?? null,
                'cause' => $cause->slug,
                'title' => $title,
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'amount' => $totalAmount,
                'meta' => array_merge(
                    $meta,
                    array_filter([
                        'cause_title' => $cause->title,
                        'cause_slug' => $cause->slug,
                        'campaign' => $campaignContext,
                        'daily_needs_summary' => $dailyNeedsSummary !== ''
                            ? Str::limit($dailyNeedsSummary, 5000, '')
                            : null,
                    ]),
                    $dailyNeedsLines !== [] ? ['daily_needs_lines' => $dailyNeedsLines] : [],
                ),
            ]);

            return [
                'cause' => $cause,
                'order' => $order,
                'package' => $package,
                'total_amount' => $totalAmount,
            ];
        });

        $order = $donation['order'];
        $cause = $donation['cause'];
        $package = $donation['package'];
        $totalAmount = $donation['total_amount'];

        /**  Razorpay Order */
        $api = new Api(config('payments.razorpay.key'), config('payments.razorpay.secret'));

        $razorpayOrder = $api->order->create([
            'amount' => (int) round($totalAmount * 100),
            'currency' => 'INR',
            'notes' => RazorpayDonationLabels::orderNotes($order, $cause, $package),
        ]);

        /**  Save provider order id */
        $order->update([
            'provider_order_id' => $razorpayOrder['id'],
        ]);

        $this->analytics->trackCheckoutStarted($request, $order->fresh());
        $this->linkTracking->attachToOrder($order->fresh(), $request->all(), $request);

        /** Return Razorpay payload */
        return response()->json([
            'provider' => 'razorpay',
            'order' => [
                'order_id' => $razorpayOrder['id'],
                'order_uuid' => $order->order_uuid,
                'thank_you_url' => DonationThankYouUrl::forOrder($order),
                'key' => config('payments.razorpay.key'),
                'amount' => $razorpayOrder['amount'],
                'name' => $request->donor_name,
                'email' => $request->donor_email,
                'contact' => $request->donor_phone,
                'description' => RazorpayDonationLabels::description(
                    $cause,
                    $package,
                    $request->input('title'),
                ),
            ],
        ]);
    }

    /**
     * 🇮🇳 INDIAN RECURRING DONATION (RAZORPAY SUBSCRIPTION)
     */
    public function razorpaySubscription(
        StoreRecurringDonationRequest $request,
        RazorpaySubscriptionService $subscriptionService
    ): JsonResponse {
        Log::info('Recurring donation request received', DonationRequestLogContext::fromInput($request->validated()));

        $frequency = (string) $request->input('frequency');
        $totalCount = $subscriptionService->resolveTotalCountForFrequency($frequency);

        $donation = DB::transaction(function () use ($request, $frequency, $totalCount): array {
            $cause = Cause::query()
                ->where('slug', $request->input('cause'))
                ->with('packages')
                ->firstOrFail();

            $campaignContext = CampaignDonationContext::fromRequest($request);

            $package = $request->filled('package_id')
                ? $cause->packages->firstWhere('id', (int) $request->input('package_id'))
                : null;

            if ($package) {
                $unitAmount = (float) $package->amount;
                $title = RazorpayDonationLabels::itemTitle($cause, $package, $request->input('title'));
            } elseif (($cause->allow_custom_amount || $request->boolean('amount_locked')) && $request->filled('amount')) {
                $unitAmount = (float) $request->input('amount');
                $title = (string) ($request->input('title')
                    ?: $campaignContext['name'] ?? null
                    ?: $cause->default_title
                    ?: 'Monthly Donation');
            } else {
                abort(422, 'Please select a donation option or enter a custom monthly amount.');
            }

            $quantity = 1;
            $totalAmount = $unitAmount * $quantity;

            $donorSnapshot = $this->donorSnapshotFromRequest($request);

            $donor = Donor::resolveFromDonationSnapshot($donorSnapshot);

            $subscription = DonationSubscription::create([
                'donor_id' => $donor->id,
                'cause_id' => $cause->id,
                'cause_package_id' => $package?->id,
                'donation_campaign_id' => $campaignContext['id'] ?? null,
                'frequency' => $frequency,
                'quantity' => $quantity,
                'unit_amount' => $unitAmount,
                'total_amount' => $totalAmount,
                'currency' => 'INR',
                'item_title' => $title,
                'total_count' => $totalCount,
                'status' => DonationSubscription::STATUS_CREATED,
                'donor_name' => $donorSnapshot['donor_name'],
                'donor_email' => $donorSnapshot['donor_email'],
                'donor_phone' => $donorSnapshot['donor_phone'],
                'date_of_birth' => $donorSnapshot['date_of_birth'],
                'pan_number' => $donorSnapshot['pan_number'],
                'address' => $donorSnapshot['address'],
                'pincode' => $donorSnapshot['pincode'],
                'city' => $donorSnapshot['city'],
                'state' => $donorSnapshot['state'],
                'country' => $donorSnapshot['country'],
                'donor_country_code' => $donorSnapshot['donor_country_code'],
                'consent_indian_citizen' => $donorSnapshot['consent_indian_citizen'],
                'consent_recurring' => $request->boolean('consent_recurring'),
                'meta' => $campaignContext ? ['campaign' => $campaignContext] : null,
            ]);

            return [
                'cause' => $cause,
                'package' => $package,
                'subscription' => $subscription,
                'total_amount' => $totalAmount,
                'unit_amount' => $unitAmount,
            ];
        });

        $subscription = $donation['subscription'];
        $cause = $donation['cause'];
        $package = $donation['package'];
        $unitAmount = $donation['unit_amount'];

        $plan = $subscriptionService->resolvePlanForDonation(
            $cause,
            $frequency,
            $unitAmount,
            $package,
            $request->boolean('amount_locked'),
        );
        $razorpaySubscription = $subscriptionService->createRazorpaySubscription($subscription, $plan);

        $this->analytics->trackSubscriptionCheckoutStarted($request, $subscription->fresh());

        return response()->json([
            'provider' => 'razorpay',
            'mode' => 'subscription',
            'subscription' => [
                'subscription_id' => $razorpaySubscription['id'],
                'subscription_uuid' => $subscription->subscription_uuid,
                'thank_you_url' => DonationThankYouUrl::forSubscription($subscription),
                'key' => config('payments.razorpay.key'),
                'name' => $request->donor_name,
                'email' => $request->donor_email,
                'contact' => $request->donor_phone,
                'description' => RazorpayDonationLabels::description($cause, $package).' ('.$subscription->frequencyLabel().')',
            ],
        ]);
    }

    public function danamojoRedirect(?string $causeSlug = null): RedirectResponse
    {
        return $this->redirectDanamojoToFrontend($causeSlug);
    }

    public function danamojoWidget(?string $causeSlug = null): RedirectResponse|View
    {
        $frontend = $this->danamojoFrontendUrl($causeSlug);
        if ($frontend !== null) {
            return redirect()->away($frontend);
        }

        // Fallback only when public frontend URL is not configured.
        return view('donate.danamojo-widget', ['causeSlug' => $causeSlug]);
    }

    private function redirectDanamojoToFrontend(?string $causeSlug): RedirectResponse
    {
        $frontend = $this->danamojoFrontendUrl($causeSlug);
        if ($frontend !== null) {
            return redirect()->away($frontend);
        }

        return redirect()->away('https://danamojo.org/donate/YOUR-DANAMOJO-PAGE');
    }

    private function danamojoFrontendUrl(?string $causeSlug): ?string
    {
        $base = rtrim((string) config('donation.public_frontend_url', ''), '/');
        if ($base === '') {
            return null;
        }

        $slug = trim((string) $causeSlug);
        if ($slug === '') {
            return $base.'/donate/danamojo-widget';
        }

        return $base.'/donate/danamojo-widget/'.rawurlencode($slug);
    }

    public function wpRazorpay(StoreDonationRequest $request): JsonResponse
    {
        $provided = (string) $request->header('X-WP-TOKEN', '');
        $expected = trim((string) config('app.wp_api_token', ''));

        if ($expected === '' || $expected === 'YOUR_WP_API_TOKEN_HERE' || ! hash_equals($expected, $provided)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Defaults for WordPress-originated checkouts (caller may override).
        if (! $request->filled('source_channel')) {
            $request->merge(['source_channel' => 'wordpress']);
        }

        if (! $request->filled('utm_source')) {
            $request->merge(['utm_source' => 'wordpress']);
        }

        if (! $request->filled('utm_medium')) {
            $request->merge(['utm_medium' => 'website']);
        }

        return $this->razorpay($request);
    }

    public function wpRazorpaySubscription(StoreRecurringDonationRequest $request, RazorpaySubscriptionService $subscriptionService): JsonResponse
    {
        if (! $request->filled('source_channel')) {
            $request->merge(['source_channel' => 'wordpress']);
        }

        if (! $request->filled('utm_source')) {
            $request->merge(['utm_source' => 'wordpress']);
        }

        if (! $request->filled('utm_medium')) {
            $request->merge(['utm_medium' => 'website']);
        }

        return $this->razorpaySubscription($request, $subscriptionService);
    }

    /**
     * @return array{
     *     donor_name: string,
     *     donor_email: string,
     *     donor_phone: string,
     *     date_of_birth: mixed,
     *     pan_number: mixed,
     *     address: string,
     *     pincode: string,
     *     city: string,
     *     state: string,
     *     country: string,
     *     donor_country_code: string,
     *     consent_indian_citizen: bool
     * }
     */
    private function donorSnapshotFromRequest(StoreDonationRequest $request): array
    {
        return [
            'donor_name' => $request->donor_name,
            'donor_email' => $request->donor_email,
            'donor_phone' => $request->donor_phone,
            'date_of_birth' => $request->date_of_birth,
            'pan_number' => $request->pan_number,
            'address' => $request->address,
            'pincode' => $request->pincode,
            'city' => $request->city,
            'state' => $request->state,
            'country' => strtoupper((string) $request->input('country', 'INDIA')),
            'donor_country_code' => strtoupper((string) $request->input('donor_country', 'IN')),
            'consent_indian_citizen' => $request->boolean('consent_indian_citizen'),
        ];
    }
}

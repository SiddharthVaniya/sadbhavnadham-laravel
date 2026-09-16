<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckPanRequirementRequest;
use App\Http\Requests\SendDonorOtpRequest;
use App\Http\Requests\StoreDonationRequest;
use App\Http\Requests\StoreLinkTrackingVisitRequest;
use App\Http\Requests\StoreRecurringDonationRequest;
use App\Http\Requests\VerifyDonorOtpRequest;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationOrder;
use App\Models\DonationSubscription;
use App\Models\Donor;
use App\Services\LinkTrackingService;
use App\Services\PostalCodeLookupService;
use App\Services\RazorpaySubscriptionService;
use App\Support\Branding;
use App\Support\DonatePublicCache;
use App\Support\DonationThankYouData;
use App\Support\DonorOtpService;
use App\Support\PublicMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class DonateApiController extends Controller
{
    public function __construct(
        private DonationController $donations,
        private LinkTrackingService $linkTracking,
        private PostalCodeLookupService $postalCodes,
        private DonorOtpService $otpService,
    ) {}

    public function causes(): JsonResponse
    {
        $payload = DonatePublicCache::remember('causes', function () {
            $causes = Cause::query()
                ->where('is_active', true)
                ->listedOnDonateIndex()
                ->with(['packages' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Cause $cause): array => $this->causePayload($cause))
                ->values();

            return ['causes' => $causes];
        });

        return $this->cachedJson($payload);
    }

    public function cause(string $slug): JsonResponse
    {
        $payload = DonatePublicCache::remember('cause.'.$slug, function () use ($slug) {
            $cause = Cause::query()
                ->where('slug', $slug)
                ->where('is_active', true)
                ->with(['packages' => fn ($query) => $query->where('is_active', true)->orderBy('sort_order')->orderBy('id')])
                ->first();

            if ($cause === null) {
                return null;
            }

            $mapped = $this->causePayload($cause);

            $otherCauses = Cause::query()
                ->where('is_active', true)
                ->listedOnDonateIndex()
                ->whereKeyNot($cause->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (Cause $item): array => $this->causeSummary($item))
                ->values()
                ->all();

            return [
                'cause' => $mapped,
                'other_causes' => $otherCauses,
                'default_package_id' => $mapped['default_package_id'],
                'default_title' => $mapped['default_title'],
                'default_amount' => $mapped['default_amount'],
            ];
        });

        if ($payload === null) {
            abort(404);
        }

        return $this->cachedJson($payload);
    }

    public function config(): JsonResponse
    {
        return $this->cachedJson(DonatePublicCache::remember('config', function () {
            return [
                'razorpay_key' => config('payments.razorpay.key'),
                'razorpay_name' => Branding::razorpayName(),
                'subscriptions_enabled' => (bool) config('payments.razorpay.subscriptions_enabled'),
                'subscription_min_amount' => (float) config('payments.razorpay.subscription_min_amount', 100),
                'subscription_max_amount' => (float) config('payments.razorpay.subscription_max_amount', 15000),
                'pan_threshold' => (int) config('donation.pan_threshold_inr', 100000),
                'min_amount' => 1,
                'max_amount' => 500000,
                'currency' => 'INR',
                'brand_name' => Branding::name(),
                'recurring_mandate_text' => Branding::recurringMandateText(),
                'payment_logos' => PublicMediaUrl::fromStoredPath(Branding::assetPath('payment_gateways')),
                'media' => [
                    'origin' => PublicMediaUrl::origin(),
                    'logo' => PublicMediaUrl::fromStoredPath(Branding::assetPath('logo_public')),
                    'upi_qr' => PublicMediaUrl::fromStoredPath(Branding::assetPath('upi_qr')),
                    'payment_logos' => PublicMediaUrl::fromStoredPath(Branding::assetPath('payment_gateways')),
                ],
            ];
        }));
    }

    public function pincode(string $pincode): JsonResponse
    {
        if (! preg_match('/^[0-9]{6}$/', $pincode)) {
            return response()->json([
                'found' => false,
                'message' => 'Please enter a valid 6-digit pincode.',
            ], 422);
        }

        try {
            $result = $this->postalCodes->lookup('IN', $pincode);
        } catch (\Throwable) {
            return response()->json([
                'found' => false,
                'message' => 'Pincode lookup is temporarily unavailable.',
            ], 503);
        }

        if ($result === null) {
            return response()->json([
                'found' => false,
                'message' => 'Pincode not found.',
            ], 404);
        }

        return response()->json([
            'found' => true,
            'city' => $result['city'],
            'state' => $result['state'],
            'country' => $result['country'],
        ]);
    }

    public function bankDetails(): JsonResponse
    {
        return $this->cachedJson(DonatePublicCache::remember('bank-details', function () {
            $bank = Branding::bank();
            $contact = Branding::contact();

            return [
                'account_name' => $bank['account_name'],
                'account_number' => $bank['account_number'],
                'ifsc' => $bank['ifsc'],
                'bank_name' => $bank['bank_name'],
                'branch' => $bank['branch'],
                'account_type' => $bank['account_type'],
                'upi_id' => $bank['upi_id'],
                'note' => $bank['note'],
                'contact' => [
                    'address' => $contact['address'],
                    'phone_primary' => $contact['phone_primary'],
                    'phone_secondary' => $contact['phone_secondary'],
                    'email' => $contact['email'],
                ],
            ];
        }));
    }

    public function panRequirement(CheckPanRequirementRequest $request): JsonResponse
    {
        return $this->donations->panRequirement($request);
    }

    public function checkout(StoreDonationRequest $request): JsonResponse
    {
        return $this->donations->razorpay($request);
    }

    public function checkoutSubscription(StoreRecurringDonationRequest $request, RazorpaySubscriptionService $subscriptions): JsonResponse
    {
        return $this->donations->razorpaySubscription($request, $subscriptions);
    }

    public function track(StoreLinkTrackingVisitRequest $request): JsonResponse
    {
        return response()->json($this->linkTracking->recordVisit($request->validated(), $request));
    }

    public function sendOtp(SendDonorOtpRequest $request): JsonResponse
    {
        return app(\App\Http\Controllers\DonorOtpController::class)->send($request, $this->otpService);
    }

    public function verifyOtp(VerifyDonorOtpRequest $request): JsonResponse
    {
        $method = (string) $request->validated('login_method');

        $result = $method === 'email'
            ? $this->otpService->verifyByEmail(
                (string) $request->validated('donor_email'),
                (string) $request->validated('otp'),
            )
            : $this->otpService->verify(
                (string) $request->validated('donor_phone'),
                (string) $request->validated('otp'),
                (string) $request->validated('phone_dial_code'),
            );

        /** @var Donor $donor */
        $donor = $result['donor'];
        $token = $donor->createToken('donate-api')->plainTextToken;

        return response()->json([
            'verified' => true,
            'token' => $token,
            'token_type' => 'Bearer',
            'profile' => $result['profile'],
            'message' => $result['message'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $donor = $request->user('sanctum');

        if (! $donor instanceof Donor) {
            abort(401);
        }

        return response()->json([
            'profile' => $this->otpService->profilePayload($donor),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user('sanctum')?->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        } elseif ($request->bearerToken()) {
            PersonalAccessToken::findToken($request->bearerToken())?->delete();
        }

        Auth::forgetGuards();

        return response()->json([
            'signed_in' => false,
            'message' => 'Signed out.',
        ]);
    }

    public function thankYou(DonationOrder $order): JsonResponse
    {
        return response()->json([
            'thank_you' => DonationThankYouData::forOrder($order),
        ]);
    }

    public function thankYouSubscription(DonationSubscription $subscription): JsonResponse
    {
        return response()->json([
            'thank_you' => DonationThankYouData::forSubscription($subscription),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function cachedJson(mixed $payload): JsonResponse
    {
        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=30, s-maxage=120, stale-while-revalidate=300');
    }

    /**
     * @return array<string, mixed>
     */
    private function causePayload(Cause $cause): array
    {
        $packages = $cause->packages
            ->map(fn (CausePackage $package): array => [
                'id' => $package->id,
                'title' => $package->title,
                'amount' => (float) $package->amount,
                'image' => PublicMediaUrl::fromStoredPath($package->image),
                'meta' => [],
                'is_default' => (bool) $package->is_default,
                'sort_order' => (int) $package->sort_order,
                'allow_recurring' => (bool) $package->allow_recurring,
            ])
            ->values()
            ->all();

        $defaultPackage = $cause->packages->firstWhere('is_default', true) ?? $cause->packages->first();

        return [
            'id' => $cause->id,
            'slug' => $cause->slug,
            'laravel_slug' => $cause->slug,
            'title' => $cause->title,
            'excerpt' => $cause->excerpt,
            'description' => $cause->description,
            'hero_image' => PublicMediaUrl::fromStoredPath($cause->hero_image),
            'icon_uri' => PublicMediaUrl::fromStoredPath($cause->icon_uri),
            'icon_uri_active' => PublicMediaUrl::fromStoredPath($cause->icon_uri_active),
            'images' => PublicMediaUrl::many($cause->images),
            'allow_custom_amount' => (bool) $cause->allow_custom_amount,
            'allow_recurring' => (bool) $cause->allow_recurring,
            'allow_weekly_recurring' => (bool) $cause->allow_weekly_recurring,
            'pan_required' => (bool) $cause->pan_required,
            'default_amount' => $defaultPackage
                ? (float) $defaultPackage->amount
                : (float) $cause->default_amount,
            'default_package_id' => $defaultPackage?->id,
            'default_title' => $cause->default_title,
            'cta_text' => $cause->cta_text,
            'allowed_frequencies' => $cause->allowedRecurringFrequencies(),
            'contact' => [
                'heading' => $cause->contact_heading,
                'address' => $cause->contact_address,
                'phone' => $cause->contact_phone,
                'email' => $cause->contact_email,
                'has_card' => $cause->hasContactCard(),
            ],
            'packages' => $packages,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function causeSummary(Cause $cause): array
    {
        return [
            'slug' => $cause->slug,
            'laravel_slug' => $cause->slug,
            'title' => $cause->title,
            'excerpt' => $cause->excerpt,
            'hero_image' => PublicMediaUrl::fromStoredPath($cause->hero_image),
            'icon_uri' => PublicMediaUrl::fromStoredPath($cause->icon_uri),
            'icon_uri_active' => PublicMediaUrl::fromStoredPath($cause->icon_uri_active),
        ];
    }
}

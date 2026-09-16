<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBrandingRequest;
use App\Support\AdminInertiaResources;
use App\Support\BrandingStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class AdminBrandingController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Admin/Branding/Edit', [
            'branding' => AdminInertiaResources::brandingForm(),
        ]);
    }

    public function update(UpdateBrandingRequest $request): RedirectResponse
    {
        BrandingStore::persist($this->buildOverrides($request));

        return redirect()
            ->route('admin.settings.branding.edit')
            ->with('status', 'Branding updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildOverrides(UpdateBrandingRequest $request): array
    {
        return [
            'name' => $request->input('name'),
            'short_name' => $request->input('short_name'),
            'legal_name' => $request->input('legal_name'),
            'tagline' => $request->input('tagline'),
            'admin_label' => $request->input('admin_label'),
            'razorpay_name' => $request->input('razorpay_name'),
            'recurring_mandate' => $request->input('recurring_mandate'),
            'receipt_thank_you' => $request->input('receipt_thank_you'),
            'payment_link_description' => $request->input('payment_link_description'),
            'assets' => [
                'logo' => $this->resolveAsset($request, 'logo', $request->input('logo_existing')),
                'logo_public' => $this->resolveAsset($request, 'logo_public', $request->input('logo_public_existing')),
                'favicon' => $this->resolveAsset($request, 'favicon', $request->input('favicon_existing')),
                'og_image' => $this->resolveAsset($request, 'og_image', $request->input('og_image_existing')),
            ],
            'seo' => [
                'canonical_url' => $request->input('canonical_url'),
                'home_description' => $request->input('seo_home_description'),
            ],
            'urls' => [
                'website' => $request->input('website_url'),
                'privacy' => $request->input('privacy_url'),
                'refund' => $request->input('refund_url'),
                'terms' => $request->input('terms_url'),
            ],
            'footer' => [
                'about' => $request->input('footer_about'),
            ],
            'contact' => [
                'address' => $request->input('contact_address'),
                'phone_primary' => $request->input('contact_phone_primary'),
                'phone_secondary' => $request->input('contact_phone_secondary'),
                'email' => $request->input('contact_email'),
            ],
            'social' => [
                'facebook' => $request->input('social_facebook'),
                'instagram' => $request->input('social_instagram'),
                'youtube' => $request->input('social_youtube'),
                'whatsapp' => $request->input('social_whatsapp'),
            ],
            'bank' => [
                'account_name' => $request->input('bank_account_name'),
                'account_number' => $request->input('bank_account_number'),
                'ifsc' => $request->input('bank_ifsc'),
                'bank_name' => $request->input('bank_name'),
                'branch' => $request->input('bank_branch'),
                'account_type' => $request->input('bank_account_type'),
                'upi_id' => $request->input('bank_upi_id'),
                'note' => $request->input('bank_note'),
            ],
        ];
    }

    private function resolveAsset(UpdateBrandingRequest $request, string $field, ?string $existing): ?string
    {
        /** @var UploadedFile|null $file */
        $file = $request->file($field);

        if ($file instanceof UploadedFile) {
            $path = $file->store('branding', 'public');

            return 'storage/'.$path;
        }

        return is_string($existing) && $existing !== '' ? $existing : null;
    }
}

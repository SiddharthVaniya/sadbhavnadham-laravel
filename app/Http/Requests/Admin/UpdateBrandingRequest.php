<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:120'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:500'],
            'admin_label' => ['nullable', 'string', 'max:120'],
            'razorpay_name' => ['nullable', 'string', 'max:120'],
            'recurring_mandate' => ['nullable', 'string', 'max:1000'],
            'receipt_thank_you' => ['nullable', 'string', 'max:500'],
            'payment_link_description' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'privacy_url' => ['nullable', 'url', 'max:255'],
            'refund_url' => ['nullable', 'url', 'max:255'],
            'terms_url' => ['nullable', 'url', 'max:255'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'seo_home_description' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'max:4096'],
            'logo_existing' => ['nullable', 'string', 'max:255'],
            'logo_public' => ['nullable', 'image', 'max:4096'],
            'logo_public_existing' => ['nullable', 'string', 'max:255'],
            'favicon' => ['nullable', 'image', 'max:2048'],
            'favicon_existing' => ['nullable', 'string', 'max:255'],
            'og_image' => ['nullable', 'image', 'max:4096'],
            'og_image_existing' => ['nullable', 'string', 'max:255'],
            'footer_about' => ['nullable', 'string', 'max:2000'],
            'contact_address' => ['nullable', 'string', 'max:1000'],
            'contact_phone_primary' => ['nullable', 'string', 'max:40'],
            'contact_phone_secondary' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'social_facebook' => ['nullable', 'url', 'max:255'],
            'social_instagram' => ['nullable', 'url', 'max:255'],
            'social_youtube' => ['nullable', 'url', 'max:255'],
            'social_whatsapp' => ['nullable', 'url', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:40'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_account_type' => ['nullable', 'string', 'max:40'],
            'bank_upi_id' => ['nullable', 'string', 'max:120'],
            'bank_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

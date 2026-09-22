<?php

namespace App\Http\Requests\Admin;

use App\Models\Cause;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCauseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Cause $cause */
        $cause = $this->route('cause');

        return [
            'slug' => ['required', 'string', 'max:255', 'unique:causes,slug,'.$cause->id],
            'title' => ['required', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],

            // Images
            'hero_image' => ['nullable', 'image', 'max:4096'],
            'hero_image_existing' => ['nullable', 'string', 'max:255'],

            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // Details
            'details_text' => ['nullable', 'string'],

            'aisensy_account_id' => ['nullable', 'exists:aisensy_accounts,id'],
            'aisensy_payment_link_campaign' => ['nullable', 'string', 'max:255'],
            'aisensy_thank_you_campaign' => ['nullable', 'string', 'max:255'],
            'aisensy_certificate_campaign' => ['nullable', 'string', 'max:255'],
            'aisensy_receipt_campaign' => ['nullable', 'string', 'max:255'],
            'certificate_template' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'certificate_template_existing' => ['nullable', 'string', 'max:255'],
            'remove_certificate_template' => ['nullable', 'boolean'],
            'certificate_template_english' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'certificate_template_english_existing' => ['nullable', 'string', 'max:255'],
            'remove_certificate_template_english' => ['nullable', 'boolean'],
            'aisensy_send_thank_you' => ['nullable', 'boolean'],
            'aisensy_send_certificate' => ['nullable', 'boolean'],
            'aisensy_send_receipt' => ['nullable', 'boolean'],
            'aisensy_thank_you_image' => ['nullable', 'image', 'max:2048'],
            'aisensy_thank_you_image_existing' => ['nullable', 'string', 'max:255'],
            'remove_aisensy_thank_you_image' => ['nullable', 'boolean'],
            'aisensy_thank_you_message_mode' => ['nullable', 'in:template,builder'],
            'aisensy_thank_you_message_template' => ['nullable', 'string'],
            'aisensy_thank_you_include_name' => ['nullable', 'boolean'],
            'aisensy_thank_you_include_amount' => ['nullable', 'boolean'],
            'aisensy_thank_you_include_cause' => ['nullable', 'boolean'],
            'aisensy_thank_you_include_receipt' => ['nullable', 'boolean'],

            'icon_uri_file' => ['nullable', 'image', 'mimes:png,jpeg,jpg,webp', 'max:2048'],
            'icon_uri_existing' => ['nullable', 'string', 'max:255'],
            'icon_uri_active_file' => ['nullable', 'image', 'mimes:png,jpeg,jpg,webp', 'max:2048'],
            'icon_uri_active_existing' => ['nullable', 'string', 'max:255'],

            // Donation config
            'allow_custom_amount' => ['nullable', 'boolean'],
            'allow_recurring' => ['nullable', 'boolean'],
            'allow_weekly_recurring' => ['nullable', 'boolean'],
            'pan_required' => ['nullable', 'boolean'],
            'default_amount' => ['nullable', 'numeric', 'min:1'],
            'default_title' => ['nullable', 'string', 'max:255'],
            'cta_text' => ['nullable', 'string', 'max:255'],
            'contact_heading' => ['nullable', 'string', 'max:255'],
            'contact_address' => ['nullable', 'string'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}

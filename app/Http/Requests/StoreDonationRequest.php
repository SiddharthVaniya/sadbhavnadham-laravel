<?php

namespace App\Http\Requests;

use App\Models\Cause;
use App\Support\Attribution\AttributionParameters;
use App\Support\PanRequirementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cause' => ['required', 'string', 'exists:causes,slug'],
            'package_id' => ['nullable', 'integer', 'exists:cause_packages,id'],
            'amount' => ['nullable', 'numeric', 'min:1', 'max:500000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'donor_name' => ['required', 'string', 'max:255', 'regex:/^(?=.*\p{L})[\p{L}\p{M}\s\'.\-()]+$/u'],
            'donor_email' => ['required', 'email', 'max:255'],
            'donor_phone' => ['required', 'regex:/^[0-9]{10}$/'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['required', 'string', 'max:1000'],
            'pincode' => ['required', 'regex:/^[0-9]{6}$/'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', 'max:120'],
            'country' => ['required', 'string', 'max:120'],
            'donor_country' => ['nullable', 'string', 'size:2'],
            'consent_indian_citizen' => ['accepted'],
            'pan_number' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'title' => ['nullable', 'string', 'max:255'],
            'daily_needs_summary' => ['nullable', 'string', 'max:5000'],
            'daily_needs_lines' => ['nullable', 'array', 'max:100'],
            'daily_needs_lines.*.id' => ['nullable', 'string', 'max:64'],
            'daily_needs_lines.*.title' => ['required_with:daily_needs_lines', 'string', 'max:255'],
            'daily_needs_lines.*.qty' => ['required_with:daily_needs_lines', 'integer', 'min:1', 'max:1000'],
            'daily_needs_lines.*.unit' => ['nullable', 'string', 'max:32'],
            'daily_needs_lines.*.qty_label' => ['nullable', 'string', 'max:64'],
            'daily_needs_lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'daily_needs_lines.*.amount' => ['required_with:daily_needs_lines', 'numeric', 'min:0'],
            'campaign_slug' => ['nullable', 'string', 'max:255'],
            'amount_locked' => ['nullable', 'boolean'],
            'visitor_id' => ['nullable', 'uuid'],
            'sid' => ['nullable', 'string', 'max:255'],
            'utm_sid' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            'utm_id' => ['nullable', 'string', 'max:120'],
            'utm_term' => ['nullable', 'string', 'max:120'],
            'fbclid' => ['nullable', 'string', 'max:2048'],
            'pid' => ['nullable', 'string', 'max:64'],
            'aid' => ['nullable', 'string', 'max:120'],
            'amt' => ['nullable', 'string', 'max:32'],
            'ptype' => ['nullable', 'string', 'max:32'],
            'landing_url' => ['nullable', 'string', 'max:2048'],
            'landing_path' => ['nullable', 'string', 'max:255'],
            'referrer' => ['nullable', 'string', 'max:512'],
            'source_channel' => ['nullable', 'string', 'max:32'],
            'donation_type' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'cause.required' => 'Please select a cause.',
            'cause.exists' => 'Please select a valid cause.',
            'package_id.exists' => 'Please select a valid donation option.',
            'amount.required' => 'Please enter a donation amount.',
            'amount.min' => 'Donation amount must be at least 1.',
            'donor_name.regex' => 'Please enter a valid full name (letters, spaces, and common punctuation only).',
            'donor_phone.regex' => 'Please enter a valid 10-digit Indian mobile number.',
            'pincode.regex' => 'Please enter a valid 6-digit pincode.',
            'consent_indian_citizen.accepted' => 'Please confirm the declaration before donating.',
            'pan_number.regex' => 'Please enter a valid PAN number.',
            'pan_number.required' => 'PAN is required when your donation reaches ₹1,00,000 or your total donations this financial year reach ₹1,00,000.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(AttributionParameters::withSidAlias($this->all()));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $cause = Cause::query()
                ->where('slug', $this->input('cause'))
                ->with('packages')
                ->first();

            if (! $cause || ! $cause->is_active) {
                $validator->errors()->add('cause', 'Please select a valid cause.');

                return;
            }

            $packageId = $this->input('package_id');
            if ($packageId) {
                $package = $cause->packages->firstWhere('id', (int) $packageId);
                if (! $package || ! $package->is_active) {
                    $validator->errors()->add('package_id', 'Please select a valid donation option.');
                }
            } else {
                if (! $cause->allow_custom_amount && ! $this->boolean('amount_locked')) {
                    $validator->errors()->add('amount', 'Please select a donation option.');
                }
                if (! $this->filled('amount')) {
                    $validator->errors()->add('amount', 'Please enter a donation amount.');
                }
            }

            if ($cause->pan_required) {
                $panRequirement = app(PanRequirementService::class);
                $totalAmount = $panRequirement->resolveDonationTotalFromRequest($this, $cause);

                if ($panRequirement->isRequired(
                    $cause,
                    $totalAmount,
                    (string) $this->input('donor_email'),
                    (string) $this->input('donor_phone'),
                ) && ! $this->filled('pan_number')) {
                    $validator->errors()->add(
                        'pan_number',
                        'PAN is required when your donation reaches ₹1,00,000 or your total donations this financial year reach ₹1,00,000.'
                    );
                }
            }
        });
    }
}

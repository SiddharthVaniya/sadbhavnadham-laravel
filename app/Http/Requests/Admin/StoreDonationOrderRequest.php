<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\NormalizesAdminDonorContact;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationOrder;
use App\Support\PanRequirementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDonationOrderRequest extends FormRequest
{
    use NormalizesAdminDonorContact;

    public function authorize(): bool
    {
        return $this->user()?->can('create', DonationOrder::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'donor_name' => ['nullable', 'string', 'max:255'],
            'donor_email' => ['nullable', 'email', 'max:255'],
            'donor_phone' => ['nullable', 'string', 'max:20'],
            'phone_dial_code' => ['nullable', 'string', 'max:5'],
            'pan_number' => ['nullable', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'pincode' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z0-9][A-Za-z0-9\s\-]{0,15}$/'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'donor_country_code' => ['nullable', 'string', 'size:2'],
            'payment_provider' => ['nullable', 'string', Rule::in(array_keys(DonationOrder::paymentProviderOptions()))],
            'total_amount' => ['required', 'numeric', 'min:1'],
            'cause_id' => ['nullable', 'integer', 'exists:causes,id'],
            'cause_package_id' => ['nullable', 'integer', 'exists:cause_packages,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'item_title' => ['nullable', 'string', 'max:255'],
            'donation_date' => ['nullable', 'date', 'before_or_equal:today'],
            'receipt_number' => ['nullable', 'integer', 'min:1'],
            'send_receipt_email' => ['sometimes', 'boolean'],
            'send_whatsapp_thank_you' => ['sometimes', 'boolean'],
            'send_whatsapp_certificate' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'pincode.regex' => 'Enter a valid postal / PIN code (letters and numbers allowed).',
            'payment_provider.in' => 'Please select a valid payment provider.',
            'total_amount.required' => 'Please provide the donation amount.',
            'total_amount.min' => 'Amount must be at least 1.',
            'pan_number.regex' => 'PAN number format is invalid.',
            'pan_number.required' => 'PAN is required when your donation reaches ₹1,00,000 or your total donations this financial year reach ₹1,00,000.',
            'receipt_number.min' => 'Receipt number must be at least 1.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAdminDonorContact();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('receipt_number') && ! $validator->errors()->has('receipt_number')) {
                $receiptNumber = (int) $this->input('receipt_number');
                $provider = (string) ($this->input('payment_provider') ?: DonationOrder::PROVIDER_OFFLINE);
                $familyProviders = DonationOrder::receiptProviderFamilyMembers($provider);

                $alreadyUsed = DonationOrder::query()
                    ->whereNotNull('receipt_number')
                    ->whereIn('payment_provider', $familyProviders)
                    ->get(['receipt_number'])
                    ->contains(fn (DonationOrder $order): bool => (int) $order->receipt_number === $receiptNumber);

                if ($alreadyUsed) {
                    $validator->errors()->add(
                        'receipt_number',
                        'This receipt number is already used by another donation for this payment provider.'
                    );
                }
            }

            if ($this->boolean('send_receipt_email') && ! $this->filled('donor_email')) {
                $validator->errors()->add(
                    'donor_email',
                    'Email is required when sending a receipt by email.'
                );
            }

            if (
                ($this->boolean('send_whatsapp_thank_you') || $this->boolean('send_whatsapp_certificate'))
                && ! $this->filled('donor_phone')
            ) {
                $validator->errors()->add(
                    'donor_phone',
                    'Phone number is required when sending WhatsApp messages.'
                );
            }

            if (
                ($this->boolean('send_whatsapp_thank_you') || $this->boolean('send_whatsapp_certificate'))
                && ! $this->filled('cause_id')
            ) {
                $validator->errors()->add(
                    'cause_id',
                    'Cause is required when sending WhatsApp messages.'
                );
            }

            $panRequirement = app(PanRequirementService::class);
            $totalAmount = (float) $this->input('total_amount', 0);
            $email = (string) $this->input('donor_email', '');
            $phone = (string) $this->input('donor_phone', '');
            $threshold = $panRequirement->threshold();

            $panRequired = $totalAmount >= $threshold;

            if (! $panRequired && $phone !== '') {
                $fyPaidTotal = $panRequirement->financialYearPaidTotal($phone);
                $panRequired = ($fyPaidTotal + $totalAmount) >= $threshold;
            }

            if ($this->filled('cause_id')) {
                $cause = Cause::query()->find((int) $this->input('cause_id'));

                if ($cause?->pan_required) {
                    $panRequired = $panRequirement->isRequired($cause, $totalAmount, $email, $phone);
                } elseif ($cause && ! $cause->pan_required) {
                    $panRequired = false;
                }

                if ($this->filled('cause_package_id')) {
                    $package = CausePackage::query()->find((int) $this->input('cause_package_id'));

                    if (! $package || $package->cause_id !== $cause?->id) {
                        $validator->errors()->add(
                            'cause_package_id',
                            'The selected package does not belong to this cause.'
                        );
                    }
                }
            }

            if ($panRequired && ! $this->filled('pan_number')) {
                $validator->errors()->add(
                    'pan_number',
                    'PAN is required when your donation reaches ₹1,00,000 or your total donations this financial year reach ₹1,00,000.'
                );
            }
        });
    }
}

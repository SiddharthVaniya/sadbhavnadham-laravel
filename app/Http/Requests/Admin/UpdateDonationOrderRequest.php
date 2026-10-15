<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\NormalizesAdminDonorContact;
use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationOrder;
use App\Support\PanRequirementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDonationOrderRequest extends FormRequest
{
    use NormalizesAdminDonorContact;

    public function authorize(): bool
    {
        $order = $this->route('donationOrder');

        return $order instanceof DonationOrder
            && $order->isPaid()
            && ($this->user()?->can('update', $order) ?? false);
    }

    public function rules(): array
    {
        if ($this->allowsAmountEdit()) {
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
                'total_amount' => ['required', 'numeric', 'min:1'],
                'cause_id' => ['nullable', 'integer', 'exists:causes,id'],
                'cause_package_id' => ['nullable', 'integer', 'exists:cause_packages,id'],
                'item_title' => ['nullable', 'string', 'max:255'],
                'quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
                'donation_date' => ['nullable', 'date', 'before_or_equal:today'],
            ];
        }

        if ($this->allowsDonorEdit()) {
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
                'cause_id' => ['required', 'integer', 'exists:causes,id'],
                'cause_package_id' => ['nullable', 'integer', 'exists:cause_packages,id'],
                'item_title' => ['nullable', 'string', 'max:255'],
                'quantity' => ['nullable', 'integer', 'min:1', 'max:9999'],
            ];
        }

        return [
            'cause_id' => ['required', 'integer', 'exists:causes,id'],
            'cause_package_id' => ['nullable', 'integer', 'exists:cause_packages,id'],
            'item_title' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'pincode.regex' => 'Enter a valid postal / PIN code (letters and numbers allowed).',
            'total_amount.required' => 'Please provide the donation amount.',
            'total_amount.min' => 'Amount must be at least 1.',
            'cause_id.required' => 'Please select a cause.',
            'pan_number.regex' => 'PAN number format is invalid.',
            'pan_number.required' => 'PAN is required when your donation reaches ₹1,00,000 or your total donations this financial year reach ₹1,00,000.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeAdminDonorContact();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('cause_id')) {
                return;
            }

            $cause = Cause::query()->find((int) $this->input('cause_id'));

            if (! $cause) {
                return;
            }

            if ($this->allowsDonorEdit() && $cause->pan_required) {
                $order = $this->route('donationOrder');
                $panRequirement = app(PanRequirementService::class);
                $totalAmount = $this->allowsAmountEdit()
                    ? (float) $this->input('total_amount', 0)
                    : (float) ($order instanceof DonationOrder ? $order->total_amount : 0);

                if ($panRequirement->isRequired(
                    $cause,
                    $totalAmount,
                    (string) $this->input('donor_email', ''),
                    (string) $this->input('donor_phone', ''),
                ) && ! $this->filled('pan_number')) {
                    $validator->errors()->add(
                        'pan_number',
                        'PAN is required when your donation reaches ₹1,00,000 or your total donations this financial year reach ₹1,00,000.'
                    );
                }
            }

            if (! $this->filled('cause_package_id')) {
                return;
            }

            $package = CausePackage::query()->find((int) $this->input('cause_package_id'));

            if (! $package || $package->cause_id !== $cause->id) {
                $validator->errors()->add(
                    'cause_package_id',
                    'The selected package does not belong to this cause.'
                );
            }
        });
    }

    private function allowsDonorEdit(): bool
    {
        $order = $this->route('donationOrder');

        return $order instanceof DonationOrder && $order->allowsAdminDonorEdit();
    }

    private function allowsAmountEdit(): bool
    {
        $order = $this->route('donationOrder');

        return $order instanceof DonationOrder && $order->allowsAdminAmountEdit();
    }
}

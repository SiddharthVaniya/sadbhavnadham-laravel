<?php

namespace App\Http\Requests\Admin;

use App\Models\Cause;
use App\Models\CausePackage;
use App\Models\DonationCampaign;
use App\Support\SubscriptionFrequency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class DonationCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        [$minAmount, $maxAmount] = $this->subscriptionAmountBounds();

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('donation_campaigns', 'slug')->ignore($this->campaignId()),
            ],
            'cause_id' => ['required', 'integer', 'exists:causes,id'],
            'cause_package_id' => ['nullable', 'integer', 'exists:cause_packages,id'],
            'amount' => ['nullable', 'numeric', 'min:1'],
            'goal_amount' => ['nullable', 'numeric', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'headline' => ['nullable', 'string', 'max:255'],
            'subheadline' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'image_existing' => ['nullable', 'string', 'max:255'],
            'remove_image' => ['nullable', 'boolean'],
            'recurring_only' => ['nullable', 'boolean'],
            'frequency' => [
                'nullable',
                'string',
                Rule::in(SubscriptionFrequency::campaignOptions()),
            ],
            'is_active' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }

    public function messages(): array
    {
        [$minAmount, $maxAmount] = $this->subscriptionAmountBounds();

        return [
            'amount.min' => "Recurring donation amount must be at least {$minAmount} INR.",
            'amount.max' => "Recurring donation amount must not exceed {$maxAmount} INR.",
            'frequency.in' => 'Please choose a valid billing frequency (monthly or weekly).',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $causeId = (int) $this->input('cause_id');
            $packageId = $this->filled('cause_package_id') ? (int) $this->input('cause_package_id') : null;

            $cause = Cause::query()->find($causeId);

            if (! $cause) {
                return;
            }

            $isRecurringOnly = $this->boolean('recurring_only', true);

            if ($isRecurringOnly && ! $cause->allowsAnyRecurring()) {
                $validator->errors()->add('cause_id', 'Selected cause must have monthly or weekly donations enabled.');
            }

            if (! $cause->is_active) {
                $validator->errors()->add('cause_id', 'Selected cause is inactive.');
            }

            if ($packageId) {
                $package = CausePackage::query()->find($packageId);

                if (! $package || $package->cause_id !== $causeId) {
                    $validator->errors()->add('cause_package_id', 'Package must belong to the selected cause.');

                    return;
                }

                if (! $package->is_active) {
                    $validator->errors()->add('cause_package_id', 'Selected package is inactive.');
                }

                if ($isRecurringOnly && ! $package->allowsRecurringDonations()) {
                    $validator->errors()->add('cause_package_id', 'Selected package must allow recurring donations.');
                }

                if ($isRecurringOnly) {
                    $this->validateSubscriptionAmount($validator, (float) $package->amount, 'cause_package_id');
                }

                return;
            }

            if (! $this->filled('amount')) {
                $validator->errors()->add('amount', 'Enter a fixed amount or choose a package.');

                return;
            }

            if ($isRecurringOnly) {
                $this->validateSubscriptionAmount($validator, (float) $this->input('amount'), 'amount');
            }
        });
    }

    /**
     * @return array{0: float, 1: float}
     */
    protected function subscriptionAmountBounds(): array
    {
        return [
            (float) config('payments.razorpay.subscription_min_amount', 100),
            (float) config('payments.razorpay.subscription_max_amount', 15000),
        ];
    }

    protected function validateSubscriptionAmount(Validator $validator, float $amount, string $field = 'amount'): void
    {
        [$minAmount, $maxAmount] = $this->subscriptionAmountBounds();

        if ($amount < $minAmount || $amount > $maxAmount) {
            $validator->errors()->add(
                $field,
                "Recurring donation amount must be between {$minAmount} and {$maxAmount} INR."
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();

        $validated['recurring_only'] = $this->boolean('recurring_only', true);
        $validated['is_active'] = $this->boolean('is_active', true);
        $validated['cause_package_id'] = $this->filled('cause_package_id') ? (int) $validated['cause_package_id'] : null;
        $validated['amount'] = $this->filled('amount') ? $validated['amount'] : null;
        $validated['goal_amount'] = $this->filled('goal_amount') ? $validated['goal_amount'] : null;
        $validated['title'] = filled($validated['title'] ?? null) ? trim((string) $validated['title']) : null;
        $validated['headline'] = filled($validated['headline'] ?? null) ? trim((string) $validated['headline']) : null;
        $validated['subheadline'] = filled($validated['subheadline'] ?? null) ? trim((string) $validated['subheadline']) : null;

        $frequency = strtolower(trim((string) ($validated['frequency'] ?? SubscriptionFrequency::MONTHLY)));
        $validated['frequency'] = $validated['recurring_only'] && in_array($frequency, SubscriptionFrequency::campaignOptions(), true)
            ? $frequency
            : SubscriptionFrequency::MONTHLY;

        unset(
            $validated['image'],
            $validated['image_existing'],
            $validated['remove_image'],
        );

        return $validated;
    }

    protected function campaignId(): ?int
    {
        $campaign = $this->route('campaign');

        return $campaign instanceof DonationCampaign ? $campaign->id : null;
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Cause;
use App\Support\CampaignDonationContext;
use App\Support\SubscriptionFrequency;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRecurringDonationRequest extends StoreDonationRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['package_id'] = ['nullable', 'integer', 'exists:cause_packages,id'];
        $rules['amount'] = ['nullable', 'numeric', 'min:1', 'max:500000'];
        $rules['quantity'] = ['nullable', 'integer', 'in:1'];
        $rules['frequency'] = [
            'required',
            'string',
            Rule::in(config('payments.razorpay.subscription_frequencies', ['monthly'])),
        ];
        $rules['consent_recurring'] = ['accepted'];
        $rules['amount_locked'] = ['nullable', 'boolean'];

        return $rules;
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'package_id.exists' => 'Please select a valid donation option.',
            'quantity.in' => 'Recurring donations support one unit per billing cycle.',
            'frequency.in' => 'Please select a valid donation frequency.',
            'consent_recurring.accepted' => 'Please authorize the recurring donation mandate to continue.',
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        parent::withValidator($validator);

        $validator->after(function (Validator $validator): void {
            if (! config('payments.razorpay.subscriptions_enabled', false)) {
                $validator->errors()->add('donation_type', 'Recurring donations are not available right now.');

                return;
            }

            $cause = Cause::query()
                ->where('slug', $this->input('cause'))
                ->with('packages')
                ->first();

            if (! $cause || ! $cause->allowsAnyRecurring()) {
                $validator->errors()->add('cause', 'Recurring donations are not enabled for this cause.');

                return;
            }

            $campaign = CampaignDonationContext::findBySlug($this->input('campaign_slug'));
            $frequency = strtolower(trim((string) $this->input('frequency')));

            if ($campaign) {
                if (! $campaign->isReadyForCheckout()) {
                    $validator->errors()->add('campaign_slug', 'This campaign is not available for checkout right now.');

                    return;
                }

                if ($campaign->recurring_only && $frequency !== $campaign->billingFrequency()) {
                    $validator->errors()->add(
                        'frequency',
                        'This campaign only accepts '.$campaign->frequencyLabel().' donations.'
                    );

                    return;
                }
            } elseif ($frequency === SubscriptionFrequency::WEEKLY && ! $cause->allowsWeeklyRecurring()) {
                $validator->errors()->add('frequency', 'Weekly donations are not enabled for this cause.');

                return;
            } elseif ($frequency === SubscriptionFrequency::MONTHLY && ! $cause->allowsMonthlyRecurring()) {
                $validator->errors()->add('frequency', 'Monthly donations are not enabled for this cause.');

                return;
            }

            $packageId = $this->filled('package_id') ? (int) $this->input('package_id') : null;
            $package = $packageId ? $cause->packages->firstWhere('id', $packageId) : null;
            $customAmount = $this->filled('amount') ? (float) $this->input('amount') : null;
            $adjective = in_array($frequency, SubscriptionFrequency::all(), true)
                ? SubscriptionFrequency::adjective($frequency)
                : 'recurring';

            if ($package) {
                if (! $package->is_active) {
                    $validator->errors()->add('package_id', 'Please select a valid donation option.');

                    return;
                }

                if (! $package->allow_recurring) {
                    $validator->errors()->add('package_id', 'Recurring donations are not enabled for this donation option.');

                    return;
                }

                $amount = (float) $package->amount;
            } elseif (($cause->allow_custom_amount || $this->boolean('amount_locked')) && $customAmount) {
                $amount = $customAmount;
            } else {
                $validator->errors()->add(
                    'amount',
                    "Please select a donation option or enter a custom {$adjective} amount."
                );

                return;
            }

            $min = (float) config('payments.razorpay.subscription_min_amount', 100);
            $max = (float) config('payments.razorpay.subscription_max_amount', 15000);

            if ($amount < $min || $amount > $max) {
                $validator->errors()->add(
                    'amount',
                    "Recurring donation amount must be between {$min} and {$max} INR."
                );
            }
        });
    }
}

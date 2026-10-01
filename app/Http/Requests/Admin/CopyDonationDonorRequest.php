<?php

namespace App\Http\Requests\Admin;

use App\Models\DonationOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopyDonationDonorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $order = $this->route('donationOrder');

        return $order instanceof DonationOrder
            && $order->isPaid()
            && $order->payment_provider === DonationOrder::PROVIDER_RAZORPAY_QR
            && ($this->user()?->can('update', $order) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $order = $this->route('donationOrder');

        return [
            'source_uuid' => [
                'required',
                'string',
                Rule::exists('donation_orders', 'order_uuid'),
                Rule::notIn([$order instanceof DonationOrder ? $order->order_uuid : null]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_uuid.required' => 'Choose a donation to copy donor details from.',
            'source_uuid.exists' => 'That donation could not be found.',
            'source_uuid.not_in' => 'Choose a different donation.',
        ];
    }
}

<?php

namespace App\Http\Requests\Admin;

use App\Services\RazorpayPaymentLinkService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRecoveryNudgeRequest extends FormRequest
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
        return [
            'order_ids' => ['required', 'array', 'min:1', 'max:50'],
            'order_ids.*' => ['integer', 'distinct', 'exists:donation_orders,id'],
            'channel' => ['nullable', 'string', Rule::in(['whatsapp', ...RazorpayPaymentLinkService::mediums()])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_ids.required' => 'Select at least one checkout to nudge.',
            'order_ids.max' => 'You can nudge at most 50 checkouts at once.',
            'channel.in' => 'Choose WhatsApp, email, or SMS as the nudge channel.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('channel')) {
            $this->merge(['channel' => 'whatsapp']);
        }
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
        ];
    }
}

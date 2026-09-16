<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CancelSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancel_reason' => ['nullable', 'string', 'max:1000'],
            'cancel_at_cycle_end' => ['nullable', 'boolean'],
        ];
    }
}

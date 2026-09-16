<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRecoveryIndexRequest extends FormRequest
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
            'duration' => ['nullable', 'string', Rule::in(['today', 'last_7_days', 'last_30_days', 'last_90_days', 'all'])],
            'status' => ['nullable', 'string', Rule::in(['pending', 'failed'])],
            'search' => ['nullable', 'string', 'max:255'],
            'nudge' => ['nullable', 'string', Rule::in(['ready', 'sent', 'no_phone'])],
        ];
    }
}

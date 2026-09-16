<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckPanRequirementRequest extends FormRequest
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
            'donor_email' => ['nullable', 'email', 'max:255'],
            'donor_phone' => ['nullable', 'regex:/^[0-9]{10}$/'],
        ];
    }
}

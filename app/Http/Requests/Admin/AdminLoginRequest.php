<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'fingerprint' => ['nullable', 'string', 'min:8', 'max:128'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $fingerprint = trim((string) $this->input('fingerprint', ''));
        $fingerprintHint = $fingerprint !== ''
            ? ' Error id: '.$fingerprint
            : '';

        return [
            'email.required' => 'Please enter your admin email address.',
            'email.email' => 'Please enter a valid email address.',
            'password.required' => 'Please enter your password.',
            'fingerprint.min' => 'Device unrecognized.'.$fingerprintHint,
            'fingerprint.max' => 'Device unrecognized.'.$fingerprintHint,
        ];
    }
}

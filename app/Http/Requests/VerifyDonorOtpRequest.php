<?php

namespace App\Http\Requests;

use App\Support\PhoneDialCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyDonorOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $method = strtolower(trim((string) $this->input('login_method', 'phone'))) ?: 'phone';

        $this->merge([
            'login_method' => in_array($method, ['phone', 'email'], true) ? $method : 'phone',
            'donor_phone' => preg_replace('/\D+/', '', (string) $this->input('donor_phone', '')),
            'donor_email' => mb_strtolower(trim((string) $this->input('donor_email', ''))),
            'phone_dial_code' => preg_replace('/\D+/', '', (string) $this->input('phone_dial_code', '91')) ?: '91',
            'donor_country_code' => strtoupper(trim((string) $this->input('donor_country_code', 'IN'))) ?: 'IN',
        ]);
    }

    public function rules(): array
    {
        $length = max(4, min(8, (int) config('donation.otp.length', 6)));
        $method = (string) $this->input('login_method', 'phone');
        $dial = (string) $this->input('phone_dial_code', '91');

        if ($method === 'email') {
            return [
                'login_method' => ['required', 'in:email,phone'],
                'donor_email' => ['required', 'email:rfc', 'max:255'],
                'otp' => ['required', 'digits:'.$length],
            ];
        }

        return [
            'login_method' => ['required', 'in:email,phone'],
            'donor_phone' => [
                'required',
                'regex:'.($dial === '91' ? '/^[0-9]{10}$/' : '/^[0-9]{6,15}$/'),
            ],
            'phone_dial_code' => ['required', 'string', Rule::in(PhoneDialCodes::dialValues())],
            'donor_country_code' => ['nullable', 'string', Rule::in(PhoneDialCodes::isoValues())],
            'otp' => ['required', 'digits:'.$length],
        ];
    }

    public function messages(): array
    {
        return [
            'donor_phone.required' => 'Please enter your mobile number.',
            'donor_phone.regex' => 'Please enter a valid mobile number for the selected country.',
            'donor_email.required' => 'Please enter your email address.',
            'donor_email.email' => 'Please enter a valid email address.',
            'phone_dial_code.in' => 'Please select a valid country code.',
            'otp.required' => 'Please enter the verification code.',
            'otp.digits' => 'Please enter a valid verification code.',
        ];
    }
}

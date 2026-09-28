<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDanamojoNotifyRequest extends FormRequest
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
            'donationInfoId' => ['required', 'integer', 'min:1'],
            'dmStatus' => ['nullable', 'string', 'max:40'],
            'dmTotalAmount' => ['nullable', 'string', 'max:40'],
            'landing_url' => ['nullable', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'sid' => ['nullable', 'string', 'max:40'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            'utm_term' => ['nullable', 'string', 'max:120'],
            'utm_id' => ['nullable', 'string', 'max:40'],
            'aid' => ['nullable', 'string', 'max:40'],
        ];
    }
}

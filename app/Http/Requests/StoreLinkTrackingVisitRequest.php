<?php

namespace App\Http\Requests;

use App\Support\Attribution\AttributionParameters;
use Illuminate\Foundation\Http\FormRequest;

class StoreLinkTrackingVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitor_id' => ['required', 'uuid'],
            'sid' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:120'],
            'utm_medium' => ['nullable', 'string', 'max:120'],
            'utm_campaign' => ['nullable', 'string', 'max:120'],
            'utm_content' => ['nullable', 'string', 'max:120'],
            'utm_id' => ['nullable', 'string', 'max:120'],
            'utm_term' => ['nullable', 'string', 'max:120'],
            'fbclid' => ['nullable', 'string', 'max:2048'],
            'pid' => ['nullable', 'string', 'max:64'],
            'aid' => ['nullable', 'string', 'max:120'],
            'amt' => ['nullable', 'string', 'max:32'],
            'ptype' => ['nullable', 'string', 'max:32'],
            'landing_url' => ['nullable', 'string', 'max:2048'],
            'page_path' => ['nullable', 'string', 'max:255'],
            'referrer' => ['nullable', 'string', 'max:512'],
            'user_agent' => ['nullable', 'string', 'max:512'],
            'device_type' => ['nullable', 'in:mobile,tablet,desktop'],
            'extra_params' => ['nullable', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(AttributionParameters::withSidAlias(
            AttributionParameters::decodeTrackingPayload($this->all())
        ));
    }
}

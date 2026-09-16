<?php

namespace App\Http\Requests\Admin;

use App\Models\Setting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSettingValueRequest extends FormRequest
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
        /** @var Setting|null $setting */
        $setting = $this->route('setting');

        if ($setting instanceof Setting && Setting::isIntegerKey($setting->key)) {
            $bounds = Setting::integerSettingRules()[$setting->key];

            return [
                'value' => ['required', 'integer', 'min:'.$bounds['min'], 'max:'.$bounds['max']],
            ];
        }

        return [
            'value' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Setting $setting */
            $setting = $this->route('setting');

            if (Setting::isToggleKey($setting->key)) {
                $validator->errors()->add('value', 'Toggle settings must be updated with the toggle endpoint.');
            }
        });
    }
}

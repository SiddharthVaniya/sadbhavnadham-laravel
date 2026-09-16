<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ImportDonorsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('export donors') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Choose a CSV file to import.',
            'file.mimes' => 'Upload a CSV file.',
            'file.max' => 'CSV must be 5MB or smaller.',
        ];
    }
}

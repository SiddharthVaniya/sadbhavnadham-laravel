<?php

namespace App\Http\Requests\Admin;

use App\Models\CausePackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRazorpayQrCodeRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'cause_id' => ['nullable', 'integer', 'exists:causes,id'],
            'cause_package_id' => ['nullable', 'integer', 'exists:cause_packages,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('cause_package_id')) {
                return;
            }

            if (! $this->filled('cause_id')) {
                $validator->errors()->add(
                    'cause_package_id',
                    'Select a cause before choosing a package.'
                );

                return;
            }

            $package = CausePackage::query()->find((int) $this->input('cause_package_id'));

            if (! $package || $package->cause_id !== (int) $this->input('cause_id')) {
                $validator->errors()->add(
                    'cause_package_id',
                    'The selected package does not belong to this cause.'
                );
            }
        });
    }

    /**
     * @return array{name?: string, description?: ?string, cause_id: ?int, cause_package_id: ?int}
     */
    public function mappingPayload(): array
    {
        $validated = $this->validated();
        $causeId = filled($validated['cause_id'] ?? null) ? (int) $validated['cause_id'] : null;

        $payload = [
            'cause_id' => $causeId,
            'cause_package_id' => $causeId && filled($validated['cause_package_id'] ?? null)
                ? (int) $validated['cause_package_id']
                : null,
        ];

        if (array_key_exists('name', $validated)) {
            $payload['name'] = $validated['name'];
        }

        if (array_key_exists('description', $validated)) {
            $payload['description'] = $validated['description'];
        }

        return $payload;
    }
}

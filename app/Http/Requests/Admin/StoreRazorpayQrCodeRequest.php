<?php

namespace App\Http\Requests\Admin;

use App\Models\CausePackage;
use App\Models\RazorpayQrCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRazorpayQrCodeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'usage' => ['required', Rule::in([RazorpayQrCode::USAGE_MULTIPLE, RazorpayQrCode::USAGE_SINGLE])],
            'fixed_amount' => ['sometimes', 'boolean'],
            'payment_amount' => [
                'nullable',
                'numeric',
                'min:1',
                'max:500000',
                Rule::requiredIf(fn () => $this->boolean('fixed_amount')),
            ],
            'cause_id' => ['nullable', 'integer', 'exists:causes,id'],
            'cause_package_id' => ['nullable', 'integer', 'exists:cause_packages,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_amount.required' => 'Enter an amount when fixed amount is enabled.',
            'payment_amount.min' => 'Amount must be at least ₹1.',
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
     * @return array{
     *     name: string,
     *     description?: ?string,
     *     usage: string,
     *     fixed_amount?: bool,
     *     payment_amount?: float|int|null,
     *     cause_id: ?int,
     *     cause_package_id: ?int
     * }
     */
    public function createPayload(): array
    {
        $validated = $this->validated();
        $causeId = filled($validated['cause_id'] ?? null) ? (int) $validated['cause_id'] : null;

        return [
            ...$validated,
            'cause_id' => $causeId,
            'cause_package_id' => $causeId && filled($validated['cause_package_id'] ?? null)
                ? (int) $validated['cause_package_id']
                : null,
        ];
    }
}

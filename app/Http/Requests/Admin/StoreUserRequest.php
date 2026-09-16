<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminPermissions;
use App\Support\StaffReferral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage users') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'referral_code' => StaffReferral::normalize($this->input('referral_code')),
            'donation_target' => $this->filled('donation_target') ? $this->integer('donation_target') : null,
            'monthly_target_amount' => $this->filled('monthly_target_amount') ? $this->integer('monthly_target_amount') : null,
            'monthly_spend_amount' => $this->filled('monthly_spend_amount') ? $this->input('monthly_spend_amount') : 0,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermissions::allPermissionNames())],
            'donation_target' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'monthly_target_amount' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'monthly_spend_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            ...StaffReferral::validationRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return StaffReferral::validationMessages();
    }
}

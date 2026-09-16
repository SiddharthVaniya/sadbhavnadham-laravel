<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use App\Support\AdminPermissions;
use App\Support\StaffReferral;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->route('user');

        if (! $user instanceof User) {
            return false;
        }

        return $this->user()?->can('manage users') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'referral_code' => StaffReferral::normalize($this->input('referral_code')),
            'donation_target' => $this->filled('donation_target') ? $this->integer('donation_target') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['nullable', 'string', 'min:6'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(AdminPermissions::allPermissionNames())],
            'donation_target' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            ...StaffReferral::validationRules($user->id),
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

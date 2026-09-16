<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMarketerMonthlyBudgetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        return AdminPermissions::userCanAny($user, [
            AdminPermissions::USER_EDIT,
            AdminPermissions::MANAGE_USERS,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'marketers' => ['required', 'array', 'min:1'],
            'marketers.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'marketers.*.target_amount' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'marketers.*.spend_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ];
    }
}

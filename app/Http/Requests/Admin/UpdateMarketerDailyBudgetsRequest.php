<?php

namespace App\Http\Requests\Admin;

use App\Support\AdminPermissions;
use Illuminate\Foundation\Http\FormRequest;

class UpdateMarketerDailyBudgetsRequest extends FormRequest
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
            'spend_date' => ['nullable', 'date', 'before_or_equal:today'],
            'marketers' => ['required', 'array', 'min:1'],
            'marketers.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'marketers.*.spend_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ];
    }
}

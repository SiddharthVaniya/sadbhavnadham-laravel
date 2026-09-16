<?php

namespace App\Http\Requests\Admin;

use App\Models\DonorTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDonorTaskRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in(DonorTask::statuses())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Enter a task title.',
            'assigned_to.exists' => 'Selected assignee is invalid.',
            'status.in' => 'Task status is invalid.',
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Staff;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $staffId = $this->route('staff');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($staffId)],
            'username' => ['nullable', 'string', 'max:50', Rule::unique('users', 'username')->ignore($staffId)],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'department' => ['nullable', 'string', 'max:100'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Staff;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStaffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @bodyParam name       string required Full name of the staff member. Example: Alice Banda
     * @bodyParam email      string required Unique email address. Example: alice@lendr.app
     * @bodyParam username   string optional Unique username (max 50 chars). Example: alice.banda
     * @bodyParam phone      string optional Contact phone number. Example: +260971000001
     * @bodyParam role       string required Staff role. One of: admin, loan_officer, accountant, branch_manager, collector, viewer. Example: loan_officer
     * @bodyParam department string optional Department name. Example: Retail Lending
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'username' => ['nullable', 'string', 'max:50', 'unique:users,username'],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'department' => ['nullable', 'string', 'max:100'],
        ];
    }
}

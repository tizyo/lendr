<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class StaffLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @bodyParam login    string required Email address or username of the staff member. Example: admin@lendr.app
     * @bodyParam password string required The account password. Example: secret
     * @bodyParam device   string optional Device name for token identification. Example: Chrome/Windows
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string'],  // email or username
            'password' => ['required', 'string'],
            'device' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'Email or username is required.',
            'password.required' => 'Password is required.',
        ];
    }
}

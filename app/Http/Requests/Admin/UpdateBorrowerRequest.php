<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBorrowerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('borrowers.edit');
    }

    public function rules(): array
    {
        $borrower = $this->route('borrower');

        return [
            'first_name'              => ['required', 'string', 'max:100'],
            'last_name'               => ['required', 'string', 'max:100'],
            'other_names'             => ['nullable', 'string', 'max:100'],
            'email'                   => ['nullable', 'email', 'max:255', Rule::unique('borrowers', 'email')->ignore($borrower->id)],
            'phone'                   => ['required', 'string', 'max:20', Rule::unique('borrowers', 'phone')->ignore($borrower->id)],
            'phone_alt'               => ['nullable', 'string', 'max:20'],
            'gender'                  => ['nullable', 'in:male,female,other'],
            'date_of_birth'           => ['nullable', 'date', 'before:today'],
            'national_id'             => ['nullable', 'string', 'max:50'],
            'occupation'              => ['nullable', 'string', 'max:100'],
            'employer'                => ['nullable', 'string', 'max:150'],
            'address'                 => ['nullable', 'string', 'max:255'],
            'city'                    => ['nullable', 'string', 'max:100'],
            'province'                => ['nullable', 'string', 'max:100'],
            'country'                 => ['nullable', 'string', 'size:2'],
            'next_of_kin_name'        => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone'       => ['nullable', 'string', 'max:20'],
            'next_of_kin_relationship' => ['nullable', 'string', 'max:50'],
        ];
    }
}

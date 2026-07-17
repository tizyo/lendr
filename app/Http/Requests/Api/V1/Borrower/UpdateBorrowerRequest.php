<?php

namespace App\Http\Requests\Api\V1\Borrower;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBorrowerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('borrowers.update');
    }

    public function rules(): array
    {
        $id = $this->route('borrower')?->id;

        return [
            'first_name'               => ['sometimes', 'string', 'max:100'],
            'last_name'                => ['sometimes', 'string', 'max:100'],
            'other_names'              => ['nullable', 'string', 'max:100'],
            'email'                    => ['nullable', 'email', 'max:255', "unique:borrowers,email,{$id}"],
            'phone'                    => ['sometimes', 'regex:/^(\+260|0)(9[5-7]|7[6-8]|6[5-7])\d{7}$/', "unique:borrowers,phone,{$id}"],
            'phone_alt'                => ['nullable', 'regex:/^(\+260|0)(9[5-7]|7[6-8]|6[5-7])\d{7}$/'],
            'gender'                   => ['nullable', 'in:male,female,other'],
            'date_of_birth'            => ['nullable', 'date', 'before:today'],
            'national_id'              => ['nullable', 'string', 'max:50'],
            'occupation'               => ['nullable', 'string', 'max:100'],
            'employer'                 => ['nullable', 'string', 'max:150'],
            'address'                  => ['nullable', 'string', 'max:255'],
            'city'                     => ['nullable', 'string', 'max:100'],
            'province'                 => ['nullable', 'string', 'max:100'],
            'country'                  => ['nullable', 'string', 'size:2'],
            'next_of_kin_name'         => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone'        => ['nullable', 'string', 'max:20'],
            'next_of_kin_relationship' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex'     => 'Phone must be a valid Zambian number (e.g. +260971234567 or 0971234567).',
            'phone_alt.regex' => 'Alternate phone must be a valid Zambian number.',
        ];
    }
}

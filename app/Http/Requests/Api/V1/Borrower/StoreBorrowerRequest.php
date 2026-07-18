<?php

namespace App\Http\Requests\Api\V1\Borrower;

use Illuminate\Foundation\Http\FormRequest;

class StoreBorrowerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('borrowers.create');
    }

    /**
     * @bodyParam first_name               string required Borrower's first name. Example: Chanda
     * @bodyParam last_name                string required Borrower's last name. Example: Mwale
     * @bodyParam other_names              string optional Middle or other names. Example: Grace
     * @bodyParam email                    string optional Email address (unique). Example: chanda@example.com
     * @bodyParam phone                    string required Zambian mobile number in E.164 or local format. Example: +260971234567
     * @bodyParam phone_alt                string optional Alternate Zambian mobile number. Example: 0961234567
     * @bodyParam gender                   string optional One of: male, female, other. Example: female
     * @bodyParam date_of_birth            string optional Date of birth (YYYY-MM-DD). Example: 1990-06-15
     * @bodyParam national_id              string optional National Registration Card number. Example: 123456/78/1
     * @bodyParam occupation               string optional Borrower's occupation. Example: Teacher
     * @bodyParam employer                 string optional Employer name. Example: Ministry of Education
     * @bodyParam address                  string optional Physical address. Example: Plot 42, Lusaka
     * @bodyParam city                     string optional City. Example: Lusaka
     * @bodyParam province                 string optional Province. Example: Lusaka
     * @bodyParam country                  string optional ISO 3166-1 alpha-2 country code. Example: ZM
     * @bodyParam next_of_kin_name         string optional Next of kin full name. Example: John Mwale
     * @bodyParam next_of_kin_phone        string optional Next of kin phone. Example: 0977000001
     * @bodyParam next_of_kin_relationship string optional Relationship to next of kin. Example: Spouse
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255', 'unique:borrowers,email'],
            'phone' => ['required', 'regex:/^(\+260|0)(9[5-7]|7[6-8]|6[5-7])\d{7}$/', 'unique:borrowers,phone'],
            'phone_alt' => ['nullable', 'regex:/^(\+260|0)(9[5-7]|7[6-8]|6[5-7])\d{7}$/'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'employer' => ['nullable', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:20'],
            'next_of_kin_relationship' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone must be a valid Zambian number (e.g. +260971234567 or 0971234567).',
            'phone_alt.regex' => 'Alternate phone must be a valid Zambian number.',
        ];
    }
}

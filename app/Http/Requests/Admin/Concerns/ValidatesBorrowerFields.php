<?php

namespace App\Http\Requests\Admin\Concerns;

use App\Models\Tenant\Borrower;
use App\Rules\UniqueNationalId;
use Illuminate\Validation\Rule;

/**
 * The borrower form's rules, shared by create and edit. A borrower is either an
 * individual (name, NRC, next of kin) or a corporate (company, PACRA number,
 * contact person); fields belonging to the other type are cleared on save.
 */
trait ValidatesBorrowerFields
{
    private const INDIVIDUAL_ONLY = [
        'last_name', 'other_names', 'gender', 'date_of_birth', 'national_id',
        'occupation', 'employer', 'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relationship',
    ];

    private const CORPORATE_ONLY = [
        'company_name', 'trading_name', 'business_type', 'industry', 'incorporation_date',
        'contact_person_name', 'contact_person_position', 'contact_person_phone', 'contact_person_email',
    ];

    protected function isCorporateSubmission(): bool
    {
        return $this->input('borrower_type') === Borrower::TYPE_CORPORATE;
    }

    /** @return array<string, mixed> */
    protected function borrowerRules(?Borrower $borrower = null): array
    {
        $corporate = $this->isCorporateSubmission();
        $ignore = $borrower?->id;

        return [
            'borrower_type' => ['nullable', Rule::in([Borrower::TYPE_INDIVIDUAL, Borrower::TYPE_CORPORATE])],

            // Individual
            'first_name' => [Rule::requiredIf(! $corporate), 'nullable', 'string', 'max:100'],
            'last_name' => [Rule::requiredIf(! $corporate), 'nullable', 'string', 'max:100'],
            'other_names' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'national_id' => ['nullable', 'string', 'max:50', new UniqueNationalId($ignore)],
            'occupation' => ['nullable', 'string', 'max:100'],
            'employer' => ['nullable', 'string', 'max:150'],
            'next_of_kin_name' => ['nullable', 'string', 'max:150'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:20'],
            'next_of_kin_relationship' => ['nullable', 'string', 'max:50'],

            // Corporate
            'company_name' => [Rule::requiredIf($corporate), 'nullable', 'string', 'max:200'],
            'trading_name' => ['nullable', 'string', 'max:200'],
            'business_type' => ['nullable', Rule::in(array_keys(Borrower::BUSINESS_TYPES))],
            'industry' => ['nullable', 'string', 'max:100'],
            'incorporation_date' => ['nullable', 'date', 'before_or_equal:today'],
            'company_reg_number' => [Rule::requiredIf($corporate), 'nullable', 'string', 'max:50',
                Rule::unique('borrowers', 'company_reg_number')->ignore($ignore)],
            'contact_person_name' => [Rule::requiredIf($corporate), 'nullable', 'string', 'max:150'],
            'contact_person_position' => ['nullable', 'string', 'max:100'],
            'contact_person_phone' => ['nullable', 'string', 'max:20'],
            'contact_person_email' => ['nullable', 'email', 'max:255'],

            // Both
            'tpin_number' => ['nullable', 'string', 'max:20', Rule::unique('borrowers', 'tpin_number')->ignore($ignore)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('borrowers', 'email')->ignore($ignore)],
            'phone' => ['required', 'string', 'max:20', Rule::unique('borrowers', 'phone')->ignore($ignore)],
            'phone_alt' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'company_reg_number' => 'PACRA registration number',
            'tpin_number' => 'TPIN',
            'contact_person_name' => 'contact person',
        ];
    }

    /**
     * Validated data ready to save: the type is always set, and the other type's
     * fields are blanked so switching an existing borrower leaves nothing stale.
     *
     * @return array<string, mixed>
     */
    public function borrowerData(): array
    {
        $data = $this->validated();
        $data['borrower_type'] = $this->isCorporateSubmission() ? Borrower::TYPE_CORPORATE : Borrower::TYPE_INDIVIDUAL;

        foreach ($this->isCorporateSubmission() ? self::INDIVIDUAL_ONLY : self::CORPORATE_ONLY as $field) {
            $data[$field] = null;
        }

        return $data;
    }
}

<?php

namespace App\Http\Requests\Api\V1\LoanType;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    /**
     * @bodyParam name                string  required Human-readable loan type name. Example: Business Loan
     * @bodyParam code                string  required Unique slug for the loan type (alpha-dash, max 20 chars). Example: business-loan
     * @bodyParam description         string  optional Brief description of the loan type. Example: Loans for small business owners
     * @bodyParam requires_collateral boolean optional Whether collateral is mandatory. Example: false
     * @bodyParam requires_guarantor  boolean optional Whether a guarantor is mandatory. Example: true
     * @bodyParam required_documents  string[] optional List of required document labels. Example: ["ID Copy","Pay Slip"]
     * @bodyParam is_active           boolean optional Whether this type is visible to loan officers. Example: true
     * @bodyParam sort_order          integer optional Display order (lower = first). Example: 1
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:loan_types,code'],
            'description' => ['nullable', 'string', 'max:500'],
            'requires_collateral' => ['boolean'],
            'requires_guarantor' => ['boolean'],
            'required_documents' => ['nullable', 'array'],
            'required_documents.*' => ['string'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}

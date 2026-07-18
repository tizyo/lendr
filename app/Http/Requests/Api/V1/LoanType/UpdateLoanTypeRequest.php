<?php

namespace App\Http\Requests\Api\V1\LoanType;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:100'],
            'code' => ['sometimes', 'string', 'max:20', 'alpha_dash', Rule::unique('loan_types', 'code')->ignore($this->route('loan_type'))],
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

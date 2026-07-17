<?php

namespace App\Http\Requests\Api\V1\Loan;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('loans.create') ?? false;
    }

    /**
     * @bodyParam borrower_id            integer required ID of the borrower. Example: 1
     * @bodyParam loan_type_id           integer required ID of the loan type. Example: 2
     * @bodyParam loan_plan_id           integer required ID of the loan plan. Example: 3
     * @bodyParam principal_amount       numeric required Loan principal in ZMW. Example: 5000.00
     * @bodyParam tenure                 integer required Repayment tenure (in the plan's tenure unit). Example: 12
     * @bodyParam loan_purpose           string optional Description of loan purpose. Example: School fees
     * @bodyParam application_date       string required Date of application (YYYY-MM-DD). Example: 2026-04-01
     * @bodyParam collateral_description string optional Description of collateral offered. Example: Toyota Corolla 2019
     * @bodyParam guarantor_name         string optional Guarantor full name. Example: Peter Banda
     * @bodyParam guarantor_phone        string optional Guarantor phone number. Example: 0971000002
     * @bodyParam guarantor_relationship string optional Relationship to guarantor. Example: Brother
     * @bodyParam notes                  string optional Internal notes. Example: Returning borrower, good history
     */
    public function rules(): array
    {
        return [
            'borrower_id'            => ['required', 'exists:borrowers,id'],
            'loan_type_id'           => ['required', 'exists:loan_types,id'],
            'loan_plan_id'           => ['required', 'exists:loan_plans,id'],
            'principal_amount'       => ['required', 'numeric', 'min:0.01'],
            'tenure'                 => ['required', 'integer', 'min:1'],
            'loan_purpose'           => ['nullable', 'string', 'max:1000'],
            'application_date'       => ['required', 'date'],
            'collateral_description' => ['nullable', 'string', 'max:1000'],
            'guarantor_name'         => ['nullable', 'string', 'max:150'],
            'guarantor_phone'        => ['nullable', 'string', 'max:20'],
            'guarantor_relationship' => ['nullable', 'string', 'max:100'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
        ];
    }
}

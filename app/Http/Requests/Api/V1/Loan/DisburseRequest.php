<?php

namespace App\Http\Requests\Api\V1\Loan;

use Illuminate\Foundation\Http\FormRequest;

class DisburseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('loans.disburse') ?? false;
    }

    /**
     * @bodyParam disbursement_method    string required Disbursement channel. One of: cash, bank_transfer, airtel_money, mtn_momo, zamtel_kwacha. Example: mobile_money
     * @bodyParam disbursement_account   string optional Account number or phone for electronic disbursement. Example: 0971234567
     * @bodyParam disbursement_reference string optional External reference / transaction ID. Example: TXN-00123
     * @bodyParam disbursement_date      string required Date funds are released (YYYY-MM-DD). Example: 2026-04-01
     * @bodyParam first_repayment_date   string optional First repayment due date; must be after disbursement_date. Example: 2026-05-01
     * @bodyParam notes                  string optional Internal notes about disbursement. Example: Paid via branch cashier
     */
    public function rules(): array
    {
        return [
            'disbursement_method' => ['required', 'in:cash,bank_transfer,airtel_money,mtn_momo,zamtel_kwacha'],
            'disbursement_account' => ['nullable', 'string', 'max:100'],
            'disbursement_reference' => ['nullable', 'string', 'max:100'],
            'disbursement_date' => ['required', 'date'],
            'first_repayment_date' => ['nullable', 'date', 'after:disbursement_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

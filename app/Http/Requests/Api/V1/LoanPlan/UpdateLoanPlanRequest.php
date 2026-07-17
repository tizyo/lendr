<?php

namespace App\Http\Requests\Api\V1\LoanPlan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'               => ['sometimes', 'string', 'max:100'],
            'code'               => ['sometimes', 'string', 'max:20', 'alpha_dash', Rule::unique('loan_plans', 'code')->ignore($this->route('loan_plan'))],
            'interest_rate'      => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'interest_type'      => ['sometimes', 'in:flat,reducing_balance,compound'],
            'interest_period'    => ['sometimes', 'in:daily,weekly,monthly,annually'],
            'min_tenure'         => ['sometimes', 'integer', 'min:1'],
            'max_tenure'         => ['sometimes', 'integer', 'min:1'],
            'tenure_type'        => ['sometimes', 'in:days,weeks,months'],
            'min_amount'         => ['sometimes', 'numeric', 'min:0.01'],
            'max_amount'         => ['sometimes', 'numeric', 'min:0.01'],
            'penalty_rate'       => ['numeric', 'min:0', 'max:100'],
            'penalty_type'       => ['in:flat,percentage'],
            'grace_period_days'  => ['integer', 'min:0'],
            'repayment_schedule' => ['sometimes', 'in:daily,weekly,bi_weekly,monthly,bullet'],
            'processing_fee'     => ['numeric', 'min:0', 'max:100'],
            'insurance_fee'      => ['numeric', 'min:0', 'max:100'],
            'is_active'          => ['boolean'],
        ];
    }
}

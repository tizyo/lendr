<?php

namespace App\Http\Requests\Api\V1\LoanPlan;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoanPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('settings.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'loan_type_id' => ['required', 'exists:loan_types,id'],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:loan_plans,code'],
            'interest_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'interest_type' => ['required', 'in:flat,reducing_balance,compound'],
            'interest_period' => ['required', 'in:daily,weekly,monthly,annually'],
            'min_tenure' => ['required', 'integer', 'min:1'],
            'max_tenure' => ['required', 'integer', 'gte:min_tenure'],
            'tenure_type' => ['required', 'in:days,weeks,months'],
            'min_amount' => ['required', 'numeric', 'min:0.01'],
            'max_amount' => ['required', 'numeric', 'gte:min_amount'],
            'penalty_rate' => ['numeric', 'min:0', 'max:100'],
            'penalty_type' => ['in:flat,percentage'],
            'grace_period_days' => ['integer', 'min:0'],
            'repayment_schedule' => ['required', 'in:daily,weekly,bi_weekly,monthly,bullet'],
            'processing_fee' => ['numeric', 'min:0', 'max:100'],
            'insurance_fee' => ['numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ];
    }
}

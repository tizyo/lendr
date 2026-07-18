<?php

namespace Database\Factories\Tenant;

use App\Enums\LoanStatus;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        static $seq = 1;
        $year = now()->format('Ym');
        $num = "LN-{$year}-".str_pad($seq++, 5, '0', STR_PAD_LEFT);

        return [
            'loan_number' => $num,
            'borrower_id' => Borrower::factory(),
            'loan_type_id' => LoanType::factory(),
            'loan_plan_id' => LoanPlan::factory(),
            'created_by' => User::factory(),
            'principal_amount' => 5000.00,
            'interest_amount' => 1500.00,
            'processing_fee' => 100.00,
            'insurance_fee' => 0.00,
            'total_payable' => 6600.00,
            'total_paid' => 0.00,
            'outstanding_balance' => 6600.00,
            'penalty_balance' => 0.00,
            'interest_rate' => 5.00,
            'interest_type' => 'flat',
            'interest_period' => 'monthly',
            'tenure' => 6,
            'tenure_type' => 'months',
            'repayment_schedule' => 'monthly',
            'penalty_rate' => 2.00,
            'grace_period_days' => 0,
            'currency' => 'ZMW',
            'status' => LoanStatus::Submitted->value,
            'application_date' => now()->toDateString(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => LoanStatus::Draft->value]);
    }

    public function submitted(): static
    {
        return $this->state(['status' => LoanStatus::Submitted->value]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => LoanStatus::Approved->value,
            'approval_date' => now()->toDateString(),
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'status' => LoanStatus::Active->value,
            'approval_date' => now()->subDays(7)->toDateString(),
            'disbursement_date' => now()->subDays(5)->toDateString(),
            'first_repayment_date' => now()->addMonths(1)->toDateString(),
            'maturity_date' => now()->addMonths(6)->toDateString(),
            'disbursement_method' => 'cash',
        ]);
    }

    public function denied(): static
    {
        return $this->state(['status' => LoanStatus::Denied->value]);
    }
}

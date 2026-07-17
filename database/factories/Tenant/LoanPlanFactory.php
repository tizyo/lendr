<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanPlanFactory extends Factory
{
    protected $model = LoanPlan::class;

    public function definition(): array
    {
        static $seq = 1;

        return [
            'loan_type_id'       => LoanType::factory(),
            'name'               => 'Standard Plan '.str_pad($seq, 3, '0', STR_PAD_LEFT),
            'code'               => 'LP'.str_pad($seq++, 3, '0', STR_PAD_LEFT),
            'interest_rate'      => 5.00,
            'interest_type'      => 'flat',
            'interest_period'    => 'monthly',
            'min_tenure'         => 1,
            'max_tenure'         => 24,
            'tenure_type'        => 'months',
            'min_amount'         => 500.00,
            'max_amount'         => 50000.00,
            'penalty_rate'       => 2.00,
            'penalty_type'       => 'percentage',
            'grace_period_days'  => 0,
            'repayment_schedule' => 'monthly',
            'processing_fee'     => 2.00,
            'insurance_fee'      => 0.00,
            'is_active'          => true,
        ];
    }

    public function reducingBalance(): static
    {
        return $this->state([
            'interest_type'   => 'reducing_balance',
            'interest_period' => 'monthly',
        ]);
    }

    public function weekly(): static
    {
        return $this->state([
            'repayment_schedule' => 'weekly',
            'tenure_type'        => 'weeks',
        ]);
    }

    public function bullet(): static
    {
        return $this->state([
            'repayment_schedule' => 'bullet',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

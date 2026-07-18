<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\LoanType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanTypeFactory extends Factory
{
    protected $model = LoanType::class;

    public function definition(): array
    {
        static $seq = 1;

        return [
            'name' => fake()->randomElement(['Personal Loan', 'Business Loan', 'Salary Loan', 'Emergency Loan', 'Agricultural Loan']).'-'.fake()->unique()->numerify('###'),
            'code' => 'LT'.str_pad($seq++, 3, '0', STR_PAD_LEFT),
            'description' => fake()->sentence(),
            'requires_collateral' => false,
            'requires_guarantor' => false,
            'required_documents' => [],
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function withCollateral(): static
    {
        return $this->state(['requires_collateral' => true]);
    }
}

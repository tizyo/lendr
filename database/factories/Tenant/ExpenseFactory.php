<?php

namespace Database\Factories\Tenant;

use App\Enums\ExpenseStatus;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        static $seq = 1;
        $year = now()->format('Ym');
        $num = "EXP-{$year}-".str_pad($seq++, 5, '0', STR_PAD_LEFT);

        return [
            'expense_number' => $num,
            'expense_category_id' => ExpenseCategory::factory(),
            'submitted_by' => User::factory(),
            'title' => fake()->sentence(4),
            'amount' => fake()->randomFloat(2, 50, 5000),
            'currency' => 'ZMW',
            'expense_date' => now()->toDateString(),
            'status' => ExpenseStatus::Draft->value,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ExpenseStatus::Draft->value]);
    }

    public function pending(): static
    {
        return $this->state([
            'status' => ExpenseStatus::Pending->value,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state([
            'status' => ExpenseStatus::Approved->value,
            'submitted_at' => now()->subDay(),
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => ExpenseStatus::Rejected->value,
            'rejection_reason' => 'Insufficient documentation.',
        ]);
    }
}

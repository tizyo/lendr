<?php

namespace Database\Factories\Tenant;

use App\Models\Tenant\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        static $seq = 1;

        return [
            'name' => fake()->randomElement(['Travel', 'Office Supplies', 'Utilities', 'Marketing', 'Training']).'-'.fake()->unique()->numerify('###'),
            'code' => 'EC'.str_pad($seq++, 3, '0', STR_PAD_LEFT),
            'icon' => null,
            'colour' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

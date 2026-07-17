<?php

namespace Database\Factories;

use App\Models\Tenant\Borrower;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BorrowerFactory extends Factory
{
    protected $model = Borrower::class;

    public function definition(): array
    {
        static $seq = 1;
        $year = now()->year;
        $number = "BOR-{$year}-".str_pad($seq++, 5, '0', STR_PAD_LEFT);

        return [
            'borrower_number'   => $number,
            'first_name'        => fake()->firstName(),
            'last_name'         => fake()->lastName(),
            'other_names'       => null,
            'phone'             => '097'.fake()->unique()->numerify('#######'),
            'email'             => fake()->unique()->safeEmail(),
            'gender'            => fake()->randomElement(['male', 'female']),
            'date_of_birth'     => fake()->dateTimeBetween('-60 years', '-18 years')->format('Y-m-d'),
            'national_id'       => fake()->numerify('######/##/#'),
            'occupation'        => fake()->jobTitle(),
            'employer'          => fake()->company(),
            'address'           => fake()->streetAddress(),
            'city'              => fake()->randomElement(['Lusaka', 'Ndola', 'Kitwe', 'Kabwe', 'Livingstone']),
            'province'          => fake()->randomElement(['Lusaka', 'Copperbelt', 'Central', 'Southern']),
            'country'           => 'ZM',
            'is_active'         => true,
            'is_blacklisted'    => false,
            'kyc_verified'      => false,
        ];
    }

    public function blacklisted(): static
    {
        return $this->state(['is_blacklisted' => true, 'blacklist_reason' => 'Previous default']);
    }

    public function kycVerified(): static
    {
        return $this->state(['kyc_verified' => true]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}

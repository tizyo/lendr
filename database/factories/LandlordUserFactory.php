<?php

namespace Database\Factories;

use App\Models\Landlord\LandlordUser;
use Illuminate\Database\Eloquent\Factories\Factory;

class LandlordUserFactory extends Factory
{
    protected $model = LandlordUser::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'is_active' => true,
        ];
    }
}

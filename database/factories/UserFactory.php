<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'username'          => fake()->unique()->userName(),
            'phone'             => '097'.fake()->numerify('#######'),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'role'              => UserRole::LoanOfficer,
            'is_active'         => true,
            'force_password_reset' => false,
            'remember_token'    => Str::random(10),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(['role' => UserRole::SuperAdmin]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}

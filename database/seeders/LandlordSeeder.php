<?php

namespace Database\Seeders;

use App\Models\Landlord\LandlordUser;
use Illuminate\Database\Seeder;

class LandlordSeeder extends Seeder
{
    public function run(): void
    {
        // Pass plain-text password — LandlordUser casts 'password' => 'hashed'
        // so the model handles bcrypt automatically. Using Hash::make() here would
        // double-hash and break authentication.
        $user = LandlordUser::firstOrCreate(
            ['email' => 'admin@lendr.app'],
            ['name' => 'Super Admin', 'is_active' => true],
        );

        // Always set password through model instance so the 'hashed' cast applies correctly.
        $user->password = 'Admin@1234!';
        $user->save();
    }
}

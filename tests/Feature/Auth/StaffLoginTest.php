<?php

use App\Enums\UserRole;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;

test('login page is accessible', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page->component('auth/Login'));
});

test('staff can login with correct credentials', function () {
    $user = User::factory()->create([
        'email'    => 'staff@test.com',
        'password' => Hash::make('password'),
        'role'     => UserRole::LoanOfficer,
        'is_active' => true,
    ]);

    $response = $this->post(route('login.post'), [
        'email'    => 'staff@test.com',
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('login fails with wrong password', function () {
    User::factory()->create([
        'email'    => 'staff@test.com',
        'password' => Hash::make('correct-password'),
    ]);

    $this->post(route('login.post'), [
        'email'    => 'staff@test.com',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('inactive staff cannot login', function () {
    User::factory()->create([
        'email'     => 'inactive@test.com',
        'password'  => Hash::make('password'),
        'is_active' => false,
    ]);

    $this->post(route('login.post'), [
        'email'    => 'inactive@test.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('login is rate limited after 5 attempts', function () {
    $email = 'throttle@test.com';

    User::factory()->create(['email' => $email, 'password' => Hash::make('password')]);

    foreach (range(1, 5) as $_) {
        $this->post(route('login.post'), ['email' => $email, 'password' => 'wrong']);
    }

    $response = $this->post(route('login.post'), ['email' => $email, 'password' => 'wrong']);
    $response->assertSessionHasErrors('email');
});

test('authenticated staff are redirected from login page', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->get(route('login'))
        ->assertRedirect(route('dashboard'));
});

test('staff can logout', function () {
    $user = User::factory()->create(['is_active' => true]);

    $this->actingAs($user)
        ->post(route('logout'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

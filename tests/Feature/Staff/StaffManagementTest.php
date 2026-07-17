<?php

use App\Enums\UserRole;
use App\Models\Tenant\User;

function adminUser(): User
{
    return actingAsAdmin();
}

function makeStaff(array $attrs = []): User
{
    return User::factory()->create(array_merge(['is_active' => true], $attrs));
}

// ─── Index ────────────────────────────────────────────────────────────────────

test('staff list returns paginated results', function () {
    $admin = adminUser();
    makeStaff();
    makeStaff();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.staff.index'))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total', 'current_page']]);
});

test('staff list can be searched by name', function () {
    $admin = adminUser();
    makeStaff(['name' => 'Alice Daka']);
    makeStaff(['name' => 'Bob Mwansa']);

    $response = $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.staff.index').'?search=Alice');

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Alice Daka');
    expect($names)->not->toContain('Bob Mwansa');
});

test('staff list can be filtered by role', function () {
    $admin = adminUser();
    makeStaff(['role' => UserRole::Cashier]);
    makeStaff(['role' => UserRole::Auditor]);

    $response = $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.staff.index').'?role=cashier');

    $roles = collect($response->json('data'))->pluck('role')->unique()->values()->toArray();
    expect($roles)->toBe(['cashier']);
});

// ─── Create ───────────────────────────────────────────────────────────────────

test('a new staff member can be created', function () {
    $admin = adminUser();

    $response = $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.staff.store'), [
            'name'     => 'Jane Mwale',
            'email'    => 'jane.mwale@lendr.test',
            'role'     => UserRole::LoanOfficer->value,
            'password' => 'Password1!',
        ]);

    $response->assertStatus(201)
             ->assertJsonPath('data.name', 'Jane Mwale')
             ->assertJsonPath('data.role', 'loan_officer');
});

test('duplicate email is rejected on create', function () {
    $admin   = adminUser();
    $existing = makeStaff(['email' => 'duplicate@lendr.test']);

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.staff.store'), [
            'name'     => 'Another',
            'email'    => 'duplicate@lendr.test',
            'role'     => UserRole::Cashier->value,
            'password' => 'Password1!',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

// ─── Show ─────────────────────────────────────────────────────────────────────

test('staff detail can be fetched', function () {
    $admin = adminUser();
    $staff = makeStaff();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.staff.show', $staff))
        ->assertOk()
        ->assertJsonPath('data.id', $staff->id);
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('staff member can be updated', function () {
    $admin = adminUser();
    $staff = makeStaff(['name' => 'Old Name']);

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.staff.update', $staff), ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');
});

// ─── Toggle Status ────────────────────────────────────────────────────────────

test('staff account can be deactivated', function () {
    $admin = adminUser();
    $staff = makeStaff(['is_active' => true]);

    $response = $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.staff.toggle-status', $staff));

    $response->assertOk();
    expect($staff->fresh()->is_active)->toBeFalse();
});

test('user cannot deactivate their own account', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.staff.toggle-status', $admin))
        ->assertStatus(422);
});

// ─── Delete ───────────────────────────────────────────────────────────────────

test('staff with no active loans can be deleted', function () {
    $admin = adminUser();
    $staff = makeStaff();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->deleteJson(route('api.v1.staff.destroy', $staff))
        ->assertOk();

    expect(User::find($staff->id))->toBeNull();
});

test('user cannot delete their own account', function () {
    $admin = adminUser();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->deleteJson(route('api.v1.staff.destroy', $admin))
        ->assertStatus(422);
});

// ─── Activity ─────────────────────────────────────────────────────────────────

test('staff activity log endpoint returns data', function () {
    $admin = adminUser();
    $staff = makeStaff();

    $this->actingAs($admin)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.staff.activity', $staff))
        ->assertOk()
        ->assertJsonStructure(['data']);
});

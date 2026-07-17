<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\User;
use Spatie\Permission\Models\Permission;

function loanOfficer(): User
{
    $user = User::factory()->create([
        'role'      => UserRole::LoanOfficer,
        'is_active' => true,
    ]);

    // Ensure permission exists and is assigned
    $permissions = [
        'borrowers.view', 'borrowers.create', 'borrowers.edit', 'borrowers.delete',
    ];

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    $user->givePermissionTo($permissions);

    return $user;
}

test('borrower list is accessible to loan officers', function () {
    $user = loanOfficer();

    $this->actingAs($user)
        ->get(route('borrowers.index'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('borrowers/Index'));
});

test('borrower list is paginated', function () {
    $user = loanOfficer();
    Borrower::factory()->count(25)->create();

    $response = $this->actingAs($user)->get(route('borrowers.index'));

    $response->assertInertia(fn ($page) =>
        $page->has('borrowers.data', 20) // 20 per page
    );
});

test('borrower list can be searched by phone', function () {
    $user = loanOfficer();
    Borrower::factory()->create(['phone' => '0971234567', 'first_name' => 'Target']);
    Borrower::factory()->count(5)->create();

    $response = $this->actingAs($user)
        ->get(route('borrowers.index', ['search' => '0971234567']));

    $response->assertInertia(fn ($page) =>
        $page->has('borrowers.data', 1)
             ->where('borrowers.data.0.phone', '0971234567')
    );
});

test('loan officer can create a borrower', function () {
    $user = loanOfficer();

    $response = $this->actingAs($user)->post(route('borrowers.store'), [
        'first_name' => 'John',
        'last_name'  => 'Banda',
        'phone'      => '0971234567',
        'country'    => 'ZM',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('borrowers', ['phone' => '0971234567', 'first_name' => 'John']);
});

test('borrower number is auto-generated on create', function () {
    $user = loanOfficer();

    $this->actingAs($user)->post(route('borrowers.store'), [
        'first_name' => 'Alice',
        'last_name'  => 'Phiri',
        'phone'      => '0961234567',
        'country'    => 'ZM',
    ]);

    $borrower = Borrower::where('phone', '0961234567')->first();

    expect($borrower->borrower_number)->toMatch('/^BOR-\d{4}-\d{5}$/');
});

test('duplicate phone number is rejected', function () {
    $user = loanOfficer();
    Borrower::factory()->create(['phone' => '0971111111']);

    $response = $this->actingAs($user)->post(route('borrowers.store'), [
        'first_name' => 'Another',
        'last_name'  => 'Person',
        'phone'      => '0971111111',
        'country'    => 'ZM',
    ]);

    $response->assertSessionHasErrors('phone');
});

test('loan officer can view borrower profile', function () {
    $user = loanOfficer();
    $borrower = Borrower::factory()->create();

    $this->actingAs($user)
        ->get(route('borrowers.show', $borrower))
        ->assertStatus(200)
        ->assertInertia(fn ($page) =>
            $page->component('borrowers/Show')
                 ->where('borrower.id', $borrower->id)
        );
});

test('loan officer can update a borrower', function () {
    $user = loanOfficer();
    $borrower = Borrower::factory()->create(['city' => 'Lusaka']);

    $this->actingAs($user)->put(route('borrowers.update', $borrower), [
        ...$borrower->only('first_name', 'last_name', 'phone', 'country'),
        'city' => 'Ndola',
    ]);

    expect($borrower->fresh()->city)->toBe('Ndola');
});

test('borrower with active loans cannot be deleted', function () {
    // This requires loan factory — will be tested fully in Phase 1.6
    // Placeholder assertion: delete route exists and responds
    $user = loanOfficer();
    $borrower = Borrower::factory()->create();

    $this->actingAs($user)
        ->delete(route('borrowers.destroy', $borrower))
        ->assertRedirect();

    // With no active loans, borrower should be soft-deleted
    $this->assertSoftDeleted('borrowers', ['id' => $borrower->id]);
});

test('unauthenticated user is redirected to login', function () {
    $this->get(route('borrowers.index'))
        ->assertRedirect(route('login'));
});

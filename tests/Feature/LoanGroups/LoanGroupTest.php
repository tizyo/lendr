<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\LoanGroup;
use App\Models\Tenant\LoanGroupMember;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function groupAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function makeGroup(array $attrs = []): LoanGroup
{
    return LoanGroup::create(array_merge([
        'name'         => 'Test Group',
        'group_number' => LoanGroup::generateGroupNumber(),
        'status'       => 'active',
        'max_members'  => 10,
    ], $attrs));
}

// ─── Group CRUD tests ─────────────────────────────────────────────────────────

test('can create a loan group', function () {
    $admin = groupAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loan-groups.store'), [
            'name'             => 'Sunrise Village Group',
            'meeting_schedule' => 'Every Monday 10AM',
            'max_members'      => 15,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.group.name', 'Sunrise Village Group');

    $this->assertDatabaseHas('loan_groups', ['name' => 'Sunrise Village Group']);
})->group('loan-groups');

test('group number is auto-generated', function () {
    $admin = groupAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loan-groups.store'), ['name' => 'Alpha Group'])
        ->assertStatus(201);

    expect($resp->json('data.group.group_number'))->toStartWith('GRP');
})->group('loan-groups');

test('create group requires a name', function () {
    $admin = groupAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loan-groups.store'), [])
        ->assertJsonValidationErrors(['name']);
})->group('loan-groups');

test('can list loan groups', function () {
    $admin = groupAdmin();
    makeGroup(); makeGroup(['name' => 'Beta Group']); makeGroup(['name' => 'Gamma Group']);

    $this->actingAs($admin)
        ->getJson(route('api.v1.loan-groups.index'))
        ->assertOk()
        ->assertJsonPath('meta.total', 3);
})->group('loan-groups');

test('can update group details', function () {
    $admin = groupAdmin();
    $group = makeGroup();

    $this->actingAs($admin)
        ->putJson(route('api.v1.loan-groups.update', $group), ['name' => 'Updated Name', 'status' => 'inactive'])
        ->assertOk()
        ->assertJsonPath('data.group.name', 'Updated Name')
        ->assertJsonPath('data.group.status', 'inactive');
})->group('loan-groups');

test('can soft-delete a group', function () {
    $admin = groupAdmin();
    $group = makeGroup();

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.loan-groups.destroy', $group))
        ->assertOk();

    $this->assertSoftDeleted('loan_groups', ['id' => $group->id]);
})->group('loan-groups');

test('show returns group with members', function () {
    $admin    = groupAdmin();
    $group    = makeGroup();
    $borrower = Borrower::factory()->create();

    LoanGroupMember::create([
        'loan_group_id' => $group->id,
        'borrower_id'   => $borrower->id,
        'role'          => 'member',
        'is_active'     => true,
        'joined_date'   => now()->toDateString(),
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loan-groups.show', $group))
        ->assertOk();

    expect($resp->json('data.group.active_members'))->toBe(1);
})->group('loan-groups');

// ─── Member management ────────────────────────────────────────────────────────

test('can add a member to a group', function () {
    $admin    = groupAdmin();
    $group    = makeGroup();
    $borrower = Borrower::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loan-groups.members.add', $group), [
            'borrower_id' => $borrower->id,
            'role'        => 'leader',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.member.role', 'leader');

    $this->assertDatabaseHas('loan_group_members', [
        'loan_group_id' => $group->id,
        'borrower_id'   => $borrower->id,
    ]);
})->group('loan-groups');

test('cannot add duplicate active member', function () {
    $admin    = groupAdmin();
    $group    = makeGroup();
    $borrower = Borrower::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loan-groups.members.add', $group), ['borrower_id' => $borrower->id]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loan-groups.members.add', $group), ['borrower_id' => $borrower->id])
        ->assertStatus(422);
})->group('loan-groups');

test('cannot exceed max_members capacity', function () {
    $admin = groupAdmin();
    $group = makeGroup(['max_members' => 2]);

    foreach (range(1, 2) as $_) {
        $borrower = Borrower::factory()->create();
        $this->actingAs($admin)
            ->postJson(route('api.v1.loan-groups.members.add', $group), ['borrower_id' => $borrower->id]);
    }

    $extra = Borrower::factory()->create();
    $this->actingAs($admin)
        ->postJson(route('api.v1.loan-groups.members.add', $group), ['borrower_id' => $extra->id])
        ->assertStatus(422);
})->group('loan-groups');

test('can remove a member from a group', function () {
    $admin    = groupAdmin();
    $group    = makeGroup();
    $borrower = Borrower::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loan-groups.members.add', $group), ['borrower_id' => $borrower->id]);

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.loan-groups.members.remove', [$group, $borrower]))
        ->assertOk();

    $this->assertDatabaseHas('loan_group_members', [
        'loan_group_id' => $group->id,
        'borrower_id'   => $borrower->id,
        'is_active'     => false,
    ]);
})->group('loan-groups');

test('unauthenticated cannot access loan groups', function () {
    $this->getJson(route('api.v1.loan-groups.index'))->assertStatus(401);
})->group('loan-groups');

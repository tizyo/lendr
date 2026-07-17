<?php

use App\Enums\ExpenseStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\User;
use Spatie\Permission\Models\Permission;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function expUser(array $permissions = [], UserRole $role = UserRole::Accountant): User
{
    $user = User::factory()->create(['role' => $role, 'is_active' => true]);

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }

    if ($permissions) {
        $user->givePermissionTo($permissions);
    }

    return $user;
}

function makeCategory(): ExpenseCategory
{
    return ExpenseCategory::factory()->create();
}

// ─── Create ───────────────────────────────────────────────────────────────────

test('an expense can be created in draft status', function () {
    $user = expUser(['expenses.create']);
    $cat  = makeCategory();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.store'), [
            'expense_category_id' => $cat->id,
            'title'               => 'Office supplies purchase',
            'amount'              => 250.00,
            'expense_date'        => now()->toDateString(),
        ]);

    $response->assertStatus(201)
             ->assertJsonPath('data.status', 'draft')
             ->assertJsonPath('data.title', 'Office supplies purchase');
});

test('expense number is generated in EXP-YYYYMM-NNNNN format', function () {
    $user = expUser(['expenses.create']);
    $cat  = makeCategory();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.store'), [
            'expense_category_id' => $cat->id,
            'title'               => 'Travel reimbursement',
            'amount'              => 500,
            'expense_date'        => now()->toDateString(),
        ]);

    $number = $response->json('data.expense_number');
    expect($number)->toMatch('/^EXP-\d{6}-\d{5}$/');
});

test('expense requires title, amount, date, and valid category', function () {
    $user = expUser(['expenses.create']);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.store'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['expense_category_id', 'title', 'amount', 'expense_date']);
});

// ─── Read / List ─────────────────────────────────────────────────────────────

test('expenses list returns paginated results', function () {
    $user = expUser();
    Expense::factory()->count(3)->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.expenses.index'))
        ->assertOk()
        ->assertJsonStructure(['data', 'meta' => ['total', 'current_page']]);
});

test('expense detail can be fetched', function () {
    $user    = expUser();
    $expense = Expense::factory()->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.expenses.show', $expense))
        ->assertOk()
        ->assertJsonPath('data.id', $expense->id);
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('a draft expense can be updated', function () {
    $user    = expUser(['expenses.edit']);
    $expense = Expense::factory()->draft()->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.expenses.update', $expense), ['title' => 'Updated title'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated title');
});

test('a pending expense cannot be edited', function () {
    $user    = expUser(['expenses.edit']);
    $expense = Expense::factory()->pending()->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.expenses.update', $expense), ['title' => 'New title'])
        ->assertStatus(422);
});

// ─── Delete ───────────────────────────────────────────────────────────────────

test('a draft expense can be deleted', function () {
    $user    = expUser(['expenses.delete']);
    $expense = Expense::factory()->draft()->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->deleteJson(route('api.v1.expenses.destroy', $expense))
        ->assertOk();

    expect(Expense::find($expense->id))->toBeNull();
});

test('an approved expense cannot be deleted', function () {
    $user    = expUser(['expenses.delete']);
    $expense = Expense::factory()->approved()->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->deleteJson(route('api.v1.expenses.destroy', $expense))
        ->assertStatus(422);
});

// ─── Submit ───────────────────────────────────────────────────────────────────

test('a draft expense can be submitted for approval', function () {
    $user    = expUser(['expenses.edit']);
    $expense = Expense::factory()->draft()->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.submit', $expense))
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');

    expect($expense->fresh()->status)->toBe(ExpenseStatus::Pending);
});

test('a rejected expense can be resubmitted', function () {
    $user    = expUser(['expenses.edit']);
    $expense = Expense::factory()->rejected()->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.submit', $expense))
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
});

// ─── Approve ─────────────────────────────────────────────────────────────────

test('a pending expense can be approved by authorised user', function () {
    $user    = expUser(['expenses.approve']);
    $expense = Expense::factory()->pending()->create(['submitted_by' => $user->id]);

    FundBalance::current(); // initialise balance

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.approve', $expense))
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($expense->fresh()->status)->toBe(ExpenseStatus::Approved);
});

test('approving an expense debits the fund balance', function () {
    $user    = expUser(['expenses.approve']);
    $expense = Expense::factory()->pending()->create([
        'submitted_by' => $user->id,
        'amount'       => 1000,
    ]);

    $balance = FundBalance::current();
    $balance->update(['available_balance' => 5000]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.approve', $expense))
        ->assertOk();

    expect((float) FundBalance::current()->available_balance)->toEqual(4000.0);
    expect((float) FundBalance::current()->total_expenses)->toEqual(1000.0);
});

test('approving requires expenses.approve permission', function () {
    // LoanOfficer has expenses.create/edit but not expenses.approve
    $user    = expUser([], UserRole::LoanOfficer);
    $expense = Expense::factory()->pending()->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.approve', $expense))
        ->assertStatus(403);
});

test('only pending expenses can be approved', function () {
    $user    = expUser(['expenses.approve']);
    $expense = Expense::factory()->draft()->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.approve', $expense))
        ->assertStatus(422);
});

// ─── Reject ──────────────────────────────────────────────────────────────────

test('a pending expense can be rejected with a reason', function () {
    $user    = expUser();
    $expense = Expense::factory()->pending()->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.reject', $expense), [
            'rejection_reason' => 'Missing receipt documentation.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($expense->fresh()->rejection_reason)->toBe('Missing receipt documentation.');
});

test('rejection requires a reason', function () {
    $user    = expUser();
    $expense = Expense::factory()->pending()->create(['submitted_by' => $user->id]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.reject', $expense), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['rejection_reason']);
});

test('rejection does not affect fund balance', function () {
    $user    = expUser();
    $expense = Expense::factory()->pending()->create([
        'submitted_by' => $user->id,
        'amount'       => 500,
    ]);

    $balance = FundBalance::current();
    $balance->update(['available_balance' => 3000]);

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expenses.reject', $expense), [
            'rejection_reason' => 'Duplicate request.',
        ])
        ->assertOk();

    expect((float) FundBalance::current()->available_balance)->toEqual(3000.0);
});

// ─── Categories ──────────────────────────────────────────────────────────────

test('expense categories list returns active categories', function () {
    $user = expUser();
    ExpenseCategory::factory()->count(3)->create();
    ExpenseCategory::factory()->inactive()->create();

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.expense-categories.index'));

    $response->assertOk();
    // Default: only active
    expect(collect($response->json('data'))->every(fn ($c) => $c['is_active'] === true))->toBeTrue();
});

test('expense category can be created', function () {
    $user = expUser();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->postJson(route('api.v1.expense-categories.store'), [
            'name' => 'Equipment',
            'code' => 'EQUIP',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.name', 'Equipment');
});

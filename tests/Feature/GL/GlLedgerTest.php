<?php

use App\Enums\UserRole;
use App\Models\Tenant\GlAccount;
use App\Models\Tenant\GlJournalEntry;
use App\Models\Tenant\User;
use App\Services\GlLedgerService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function glAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function seedDefaultAccounts(): void
{
    foreach (GlAccount::defaultAccounts() as $data) {
        GlAccount::firstOrCreate(['code' => $data['code']], $data);
    }
}

// ─── Chart of Accounts ────────────────────────────────────────────────────────

test('can seed default chart of accounts', function () {
    $admin = glAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.gl.accounts.seed'))
        ->assertOk()
        ->assertJsonPath('message', 'Chart of accounts seeded.');

    $this->assertDatabaseHas('gl_accounts', ['code' => '1001', 'name' => 'Cash on Hand']);
    $this->assertDatabaseHas('gl_accounts', ['code' => '1100', 'name' => 'Loans Receivable']);
    $this->assertDatabaseHas('gl_accounts', ['code' => '4001', 'name' => 'Interest Income']);
})->group('gl');

test('seed is idempotent — second call does not duplicate accounts', function () {
    $admin = glAdmin();

    $this->actingAs($admin)->postJson(route('api.v1.gl.accounts.seed'));
    $this->actingAs($admin)->postJson(route('api.v1.gl.accounts.seed'));

    $this->assertSame(1, GlAccount::where('code', '1001')->count());
})->group('gl');

test('can list accounts with balances', function () {
    $admin = glAdmin();
    seedDefaultAccounts();

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.gl.accounts'))
        ->assertOk();

    $accounts = $response->json('data.accounts');
    expect($accounts)->not->toBeEmpty();

    $cash = collect($accounts)->firstWhere('code', '1001');
    expect($cash)->toHaveKeys(['id', 'code', 'name', 'type', 'balance', 'is_active']);
})->group('gl');

test('can create a new gl account', function () {
    $admin = glAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.gl.accounts.create'), [
            'code' => '9999',
            'name' => 'Test Reserve Account',
            'type' => 'equity',
        ])
        ->assertStatus(201)
        ->assertJsonPath('message', 'Account created.');

    $this->assertDatabaseHas('gl_accounts', ['code' => '9999', 'type' => 'equity']);
})->group('gl');

test('create account rejects duplicate code', function () {
    $admin = glAdmin();
    GlAccount::create(['code' => '1001', 'name' => 'Cash', 'type' => 'asset']);

    $this->actingAs($admin)
        ->postJson(route('api.v1.gl.accounts.create'), [
            'code' => '1001',
            'name' => 'Another Cash',
            'type' => 'asset',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
})->group('gl');

test('create account rejects invalid type', function () {
    $admin = glAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.gl.accounts.create'), [
            'code' => '9998',
            'name' => 'Bad Type',
            'type' => 'savings',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
})->group('gl');

// ─── Journal Entries ──────────────────────────────────────────────────────────

test('can post a balanced journal entry', function () {
    $admin = glAdmin();
    seedDefaultAccounts();

    $this->actingAs($admin)
        ->postJson(route('api.v1.gl.entries.create'), [
            'description' => 'Test disbursement entry',
            'entry_date'  => now()->toDateString(),
            'lines'       => [
                ['account_code' => '1100', 'side' => 'debit',  'amount' => 5000],
                ['account_code' => '1001', 'side' => 'credit', 'amount' => 5000],
            ],
        ])
        ->assertStatus(201)
        ->assertJsonPath('message', 'Journal entry posted.');

    $this->assertDatabaseHas('gl_journal_entries', ['description' => 'Test disbursement entry']);
    $this->assertSame(2, \App\Models\Tenant\GlJournalLine::count());
})->group('gl');

test('posting unbalanced entry returns error', function () {
    $admin = glAdmin();
    seedDefaultAccounts();

    $this->actingAs($admin)
        ->postJson(route('api.v1.gl.entries.create'), [
            'description' => 'Unbalanced entry',
            'entry_date'  => now()->toDateString(),
            'lines'       => [
                ['account_code' => '1100', 'side' => 'debit',  'amount' => 5000],
                ['account_code' => '1001', 'side' => 'credit', 'amount' => 4000],
            ],
        ])
        ->assertUnprocessable();
})->group('gl');

test('can list journal entries', function () {
    $admin = glAdmin();
    seedDefaultAccounts();

    $ledger = app(GlLedgerService::class);
    $ledger->post('Test entry 1', [
        ['account_code' => '1100', 'side' => 'debit',  'amount' => 1000],
        ['account_code' => '1001', 'side' => 'credit', 'amount' => 1000],
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.gl.entries'))
        ->assertOk();

    $entries = $response->json('data.entries.data');
    expect($entries)->not->toBeEmpty();
    expect($entries[0])->toHaveKeys(['reference', 'entry_date', 'description', 'lines']);
})->group('gl');

test('journal entries can be filtered by account_code', function () {
    $admin = glAdmin();
    seedDefaultAccounts();

    $ledger = app(GlLedgerService::class);
    $ledger->post('Entry touching 1100', [
        ['account_code' => '1100', 'side' => 'debit',  'amount' => 2000],
        ['account_code' => '1001', 'side' => 'credit', 'amount' => 2000],
    ]);
    $ledger->post('Entry NOT touching 1100', [
        ['account_code' => '4001', 'side' => 'credit', 'amount' => 500],
        ['account_code' => '1002', 'side' => 'debit',  'amount' => 500],
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.gl.entries', ['account_code' => '1100']))
        ->assertOk();

    $entries = $response->json('data.entries.data');
    expect(count($entries))->toBe(1);
    expect($entries[0]['description'])->toBe('Entry touching 1100');
})->group('gl');

test('journal entry requires at least 2 lines', function () {
    $admin = glAdmin();
    seedDefaultAccounts();

    $this->actingAs($admin)
        ->postJson(route('api.v1.gl.entries.create'), [
            'description' => 'Too few lines',
            'entry_date'  => now()->toDateString(),
            'lines'       => [
                ['account_code' => '1100', 'side' => 'debit', 'amount' => 5000],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines']);
})->group('gl');

test('journal entry rejects unknown account code', function () {
    $admin = glAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.gl.entries.create'), [
            'description' => 'Unknown account',
            'entry_date'  => now()->toDateString(),
            'lines'       => [
                ['account_code' => 'XXXX', 'side' => 'debit',  'amount' => 1000],
                ['account_code' => 'YYYY', 'side' => 'credit', 'amount' => 1000],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.account_code', 'lines.1.account_code']);
})->group('gl');

// ─── Trial Balance ────────────────────────────────────────────────────────────

test('trial balance returns all accounts with totals', function () {
    $admin = glAdmin();
    seedDefaultAccounts();

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.gl.trial-balance'))
        ->assertOk();

    expect($response->json('data'))->toHaveKeys(['accounts', 'total_debits', 'total_credits', 'is_balanced']);
})->group('gl');

test('trial balance is balanced after posting balanced entries', function () {
    $admin = glAdmin();
    seedDefaultAccounts();

    $ledger = app(GlLedgerService::class);
    $ledger->post('Balanced entry', [
        ['account_code' => '1100', 'side' => 'debit',  'amount' => 10000],
        ['account_code' => '1001', 'side' => 'credit', 'amount' => 10000],
    ]);

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.gl.trial-balance'))
        ->assertOk();

    expect($response->json('data.is_balanced'))->toBeTrue();
})->group('gl');

test('account balance reflects posted journal entries', function () {
    seedDefaultAccounts();

    $ledger = app(GlLedgerService::class);
    $ledger->post('Disburse 5000', [
        ['account_code' => '1100', 'side' => 'debit',  'amount' => 5000],
        ['account_code' => '1001', 'side' => 'credit', 'amount' => 5000],
    ]);

    $loansReceivable = GlAccount::where('code', '1100')->first();
    expect($loansReceivable->balance())->toBe(5000.0);

    $cashOnHand = GlAccount::where('code', '1001')->first();
    expect($cashOnHand->balance())->toBe(-5000.0); // cash reduced
})->group('gl');

// ─── GL Ledger Service ────────────────────────────────────────────────────────

test('GlLedgerService::post creates balanced entry', function () {
    seedDefaultAccounts();

    $ledger = app(GlLedgerService::class);
    $entry  = $ledger->post(
        'Manual test entry',
        [
            ['account_code' => '1002', 'side' => 'debit',  'amount' => 2000],
            ['account_code' => '3001', 'side' => 'credit', 'amount' => 2000],
        ]
    );

    expect($entry->isBalanced())->toBeTrue();
    expect($entry->lines)->toHaveCount(2);
})->group('gl');

test('GlLedgerService::post throws on unbalanced entry', function () {
    seedDefaultAccounts();

    $ledger = app(GlLedgerService::class);

    expect(fn () => $ledger->post(
        'Unbalanced',
        [
            ['account_code' => '1002', 'side' => 'debit',  'amount' => 2000],
            ['account_code' => '3001', 'side' => 'credit', 'amount' => 1000],
        ]
    ))->toThrow(\RuntimeException::class);
})->group('gl');

test('journal entry references are sequential and unique', function () {
    seedDefaultAccounts();

    $ledger = app(GlLedgerService::class);
    $e1     = $ledger->post('Entry 1', [
        ['account_code' => '1100', 'side' => 'debit',  'amount' => 100],
        ['account_code' => '1001', 'side' => 'credit', 'amount' => 100],
    ]);
    $e2     = $ledger->post('Entry 2', [
        ['account_code' => '1100', 'side' => 'debit',  'amount' => 200],
        ['account_code' => '1001', 'side' => 'credit', 'amount' => 200],
    ]);

    expect($e1->reference)->not->toEqual($e2->reference);
    expect($e1->reference)->toStartWith('JNL-');
})->group('gl');

// ─── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated user cannot access gl endpoints', function () {
    $this->getJson(route('api.v1.gl.accounts'))->assertUnauthorized();
    $this->getJson(route('api.v1.gl.entries'))->assertUnauthorized();
    $this->getJson(route('api.v1.gl.trial-balance'))->assertUnauthorized();
})->group('gl');

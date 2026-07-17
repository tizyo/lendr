<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\GlAccount;
use App\Models\Tenant\GlJournalEntry;
use App\Models\Tenant\GlJournalLine;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function fsAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function seedGlAccounts(): void
{
    foreach (GlAccount::defaultAccounts() as $data) {
        GlAccount::firstOrCreate(['code' => $data['code']], $data + ['is_active' => true]);
    }
}

function postGlEntry(string $date, array $lines): void
{
    $entry = GlJournalEntry::create([
        'reference'   => 'TST-' . uniqid(),
        'entry_date'  => $date,
        'description' => 'Test entry',
    ]);

    foreach ($lines as [$code, $side, $amount]) {
        $account = GlAccount::where('code', $code)->firstOrFail();
        GlJournalLine::create([
            'journal_entry_id' => $entry->id,
            'account_id'       => $account->id,
            'side'             => $side,
            'amount'           => $amount,
        ]);
    }
}

// ─── Balance Sheet ─────────────────────────────────────────────────────────────

test('balance sheet returns asset, liability, equity sections', function () {
    $admin = fsAdmin();
    seedGlAccounts();

    // DR Loans Receivable 5000 | CR Capital Fund 5000
    postGlEntry(now()->toDateString(), [
        ['1100', 'debit',  5000],
        ['3001', 'credit', 5000],
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.financial-statements.balance-sheet'))
        ->assertOk();

    expect((float) $resp->json('data.total_assets'))->toBeGreaterThan(0)
        ->and($resp->json('data.assets'))->not->toBeEmpty()
        ->and($resp->json('data.equity'))->not->toBeEmpty();
});

test('balance sheet net_assets equals assets minus liabilities', function () {
    $admin = fsAdmin();
    seedGlAccounts();

    postGlEntry(now()->toDateString(), [
        ['1001', 'debit',  10000],
        ['3001', 'credit', 10000],
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.financial-statements.balance-sheet'))
        ->assertOk();

    $data = $resp->json('data');
    expect((float) $data['net_assets'])->toEqual(round((float) $data['total_assets'] - (float) $data['total_liabilities'], 2));
});

test('balance sheet respects as_of date — future entries excluded', function () {
    $admin = fsAdmin();
    seedGlAccounts();

    // Entry yesterday (should be included)
    postGlEntry(now()->subDay()->toDateString(), [
        ['1001', 'debit',  1000],
        ['3001', 'credit', 1000],
    ]);

    // Entry tomorrow (should be excluded)
    postGlEntry(now()->addDay()->toDateString(), [
        ['1001', 'debit',  9999],
        ['3001', 'credit', 9999],
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.financial-statements.balance-sheet', ['as_of' => now()->toDateString()]))
        ->assertOk();

    $cashRow = collect($resp->json('data.assets'))->firstWhere('code', '1001');
    expect((float) $cashRow['balance'])->toBe(1000.0);
});

// ─── Income Statement ──────────────────────────────────────────────────────────

test('income statement returns income and expenses with net income', function () {
    $admin = fsAdmin();
    seedGlAccounts();

    // DR Cash 500 | CR Interest Income 500
    postGlEntry(now()->toDateString(), [
        ['1001', 'debit',  500],
        ['4001', 'credit', 500],
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.financial-statements.income-statement'))
        ->assertOk();

    expect((float) $resp->json('data.total_income'))->toBe(500.0)
        ->and((float) $resp->json('data.net_income'))->toBe(500.0);
});

test('income statement filters by date range', function () {
    $admin = fsAdmin();
    seedGlAccounts();

    // Entry within range
    postGlEntry(now()->subDays(5)->toDateString(), [
        ['1001', 'debit',  200],
        ['4001', 'credit', 200],
    ]);

    // Entry outside range
    postGlEntry(now()->subDays(40)->toDateString(), [
        ['1001', 'debit',  999],
        ['4001', 'credit', 999],
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.financial-statements.income-statement', [
            'from' => now()->subDays(10)->toDateString(),
            'to'   => now()->toDateString(),
        ]))
        ->assertOk();

    expect((float) $resp->json('data.total_income'))->toBe(200.0);
});

// ─── Cash Flow ────────────────────────────────────────────────────────────────

test('cash flow inflows and outflows are separated correctly', function () {
    $admin = fsAdmin();
    seedGlAccounts();

    // Inflow: DR Cash 5000 | CR Loans Receivable 5000 (payment received)
    postGlEntry(now()->toDateString(), [
        ['1001', 'debit',  5000],
        ['1100', 'credit', 5000],
    ]);

    // Outflow: DR Loans Receivable 3000 | CR Cash 3000 (disbursement)
    postGlEntry(now()->toDateString(), [
        ['1100', 'debit',  3000],
        ['1001', 'credit', 3000],
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.financial-statements.cash-flow'))
        ->assertOk();

    expect((float) $resp->json('data.inflows'))->toBe(5000.0)
        ->and((float) $resp->json('data.outflows'))->toBe(3000.0)
        ->and((float) $resp->json('data.net_flow'))->toBe(2000.0);
});

// ─── Portfolio at Risk ─────────────────────────────────────────────────────────

test('portfolio at risk returns par buckets', function () {
    $admin    = fsAdmin();
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    $loan = Loan::factory()->create([
        'borrower_id'         => $borrower->id,
        'loan_type_id'        => $type->id,
        'loan_plan_id'        => $plan->id,
        'status'              => LoanStatus::Active,
        'outstanding_balance' => 10000,
    ]);

    // 45 days overdue — PAR 31-60 bucket
    LoanSchedule::create([
        'loan_id'           => $loan->id,
        'instalment_number' => 1,
        'due_date'          => now()->subDays(45)->toDateString(),
        'principal_due'     => 1000,
        'interest_due'      => 100,
        'total_due'         => 1100,
        'outstanding'       => 1100,
        'is_paid'           => false,
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.financial-statements.par'))
        ->assertOk();

    expect((float) $resp->json('data.total_portfolio'))->toBe(10000.0)
        ->and((float) $resp->json('data.buckets.par_31_60.amount'))->toBe(10000.0);
});

test('unauthenticated cannot access financial statement endpoints', function () {
    $this->getJson(route('api.v1.financial-statements.balance-sheet'))->assertUnauthorized();
    $this->getJson(route('api.v1.financial-statements.income-statement'))->assertUnauthorized();
    $this->getJson(route('api.v1.financial-statements.cash-flow'))->assertUnauthorized();
    $this->getJson(route('api.v1.financial-statements.par'))->assertUnauthorized();
});

<?php

use App\Enums\UserRole;
use App\Models\Tenant\BankStatement;
use App\Models\Tenant\BankTransaction;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use App\Models\Tenant\User;
use App\Services\ReconciliationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function reconAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function sampleCsv(array $rows = []): string
{
    $default = [
        ['2026-03-15', 'REF001', 'Payment received', '1000.00', 'credit'],
        ['2026-03-16', 'REF002', 'Transfer in',      '500.00',  'credit'],
        ['2026-03-17', 'REF003', 'Bank charges',     '25.00',   'debit'],
    ];

    $lines   = $rows ?: $default;
    $header  = "date,reference,description,amount,type\n";
    $content = $header . implode("\n", array_map(fn ($r) => implode(',', $r), $lines));
    return $content;
}

// ─── Import ───────────────────────────────────────────────────────────────────

test('can import a bank statement CSV', function () {
    $admin = reconAdmin();
    Storage::fake('local');

    $file = UploadedFile::fake()->createWithContent('statement.csv', sampleCsv());

    $response = $this->actingAs($admin)
        ->postJson(route('api.v1.reconciliation.import'), [
            'file'      => $file,
            'bank_name' => 'Zanaco',
        ]);

    $response->assertCreated();
    expect(BankStatement::count())->toBe(1);
    expect(BankTransaction::count())->toBe(3);
    expect($response->json('data.total_rows'))->toBe(3);
    expect($response->json('data.bank_name'))->toBe('Zanaco');
});

test('import rejects non-csv files', function () {
    $admin = reconAdmin();
    $file  = UploadedFile::fake()->image('photo.jpg');

    $response = $this->actingAs($admin)
        ->postJson(route('api.v1.reconciliation.import'), ['file' => $file]);

    $response->assertStatus(422);
});

// ─── Reconciliation ───────────────────────────────────────────────────────────

test('reconcile auto-matches transactions by reference', function () {
    $admin    = reconAdmin();
    $borrower = Borrower::factory()->create();
    $loan     = Loan::factory()->active()->create(['borrower_id' => $borrower->id]);

    // Create a payment with matching reference
    $payment = Payment::factory()->create([
        'loan_id'   => $loan->id,
        'reference' => 'REF001',
        'amount'    => 1000.00,
        'payment_date' => '2026-03-15',
    ]);

    $service   = app(ReconciliationService::class);
    $statement = $service->importCsv(sampleCsv(), 'test.csv', $admin->id);
    $result    = $service->reconcile($statement);

    expect($result['matched'])->toBe(1); // REF001 matched
    expect(BankTransaction::where('match_status', 'matched')->count())->toBe(1);
});

test('reconcile auto-matches transactions by date and amount', function () {
    $admin    = reconAdmin();
    $borrower = Borrower::factory()->create();
    $loan     = Loan::factory()->active()->create(['borrower_id' => $borrower->id]);

    Payment::factory()->create([
        'loan_id'   => $loan->id,
        'reference' => 'DIFFERENT_REF',
        'amount'    => 500.00,
        'payment_date' => '2026-03-16',
    ]);

    $service   = app(ReconciliationService::class);
    $statement = $service->importCsv(sampleCsv(), 'test.csv', $admin->id);
    $result    = $service->reconcile($statement);

    // REF002: 500.00 on 2026-03-16 matches the payment
    expect($result['matched'])->toBeGreaterThanOrEqual(1);
});

test('can list statements via API', function () {
    $admin   = reconAdmin();
    $service = app(ReconciliationService::class);
    $service->importCsv(sampleCsv(), 'test.csv', $admin->id);

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.reconciliation.index'));

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(1);
});

test('can trigger reconcile via API', function () {
    $admin     = reconAdmin();
    $service   = app(ReconciliationService::class);
    $statement = $service->importCsv(sampleCsv(), 'test.csv', $admin->id);

    $response = $this->actingAs($admin)
        ->postJson(route('api.v1.reconciliation.reconcile', $statement));

    $response->assertOk();
    expect($response->json('data.status'))->toBe('processed');
});

test('can view unmatched transaction queue', function () {
    $admin     = reconAdmin();
    $service   = app(ReconciliationService::class);
    $statement = $service->importCsv(sampleCsv(), 'test.csv', $admin->id);

    $response = $this->actingAs($admin)
        ->getJson(route('api.v1.reconciliation.unmatched', $statement));

    $response->assertOk();
    // All 2 credit rows are unmatched initially (1 debit skipped in reconcile)
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(1);
});

// ─── Manual Match / Ignore ────────────────────────────────────────────────────

test('can manually match a bank transaction to a payment', function () {
    $admin    = reconAdmin();
    $borrower = Borrower::factory()->create();
    $loan     = Loan::factory()->active()->create(['borrower_id' => $borrower->id]);
    $payment  = Payment::factory()->create(['loan_id' => $loan->id, 'amount' => 1000]);

    $service   = app(ReconciliationService::class);
    $statement = $service->importCsv(sampleCsv(), 'test.csv', $admin->id);
    $tx        = $statement->transactions()->where('reference', 'REF001')->first();

    $response = $this->actingAs($admin)
        ->postJson(route('api.v1.reconciliation.match', $tx), [
            'payment_id' => $payment->id,
            'notes'      => 'Manually verified',
        ]);

    $response->assertOk();
    expect($tx->fresh()->match_status)->toBe('matched');
    expect($tx->fresh()->matched_payment_id)->toBe($payment->id);
});

test('can ignore a bank transaction', function () {
    $admin     = reconAdmin();
    $service   = app(ReconciliationService::class);
    $statement = $service->importCsv(sampleCsv(), 'test.csv', $admin->id);
    $tx        = $statement->transactions()->first();

    $response = $this->actingAs($admin)
        ->postJson(route('api.v1.reconciliation.ignore', $tx), [
            'reason' => 'Internal transfer',
        ]);

    $response->assertOk();
    expect($tx->fresh()->match_status)->toBe('ignored');
});

test('reconciliation report shows match rate', function () {
    $admin     = reconAdmin();
    $service   = app(ReconciliationService::class);
    $statement = $service->importCsv(sampleCsv(), 'test.csv', $admin->id);

    $report = $service->report($statement);

    expect($report)->toHaveKeys(['match_rate', 'total_rows', 'matched_count', 'unmatched_count']);
    expect($report['total_rows'])->toBe(3);
});

test('unauthenticated request returns 401', function () {
    $this->getJson(route('api.v1.reconciliation.index'))->assertUnauthorized();
});

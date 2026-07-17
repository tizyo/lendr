<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function bulkAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function csvFile(string $content, string $name = 'import.csv'): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv_');
    file_put_contents($path, $content);
    return new UploadedFile($path, $name, 'text/csv', null, true);
}

function submittedLoan(User $admin): Loan
{
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    return Loan::factory()->create([
        'borrower_id'    => $borrower->id,
        'loan_type_id'   => $type->id,
        'loan_plan_id'   => $plan->id,
        'created_by'     => $admin->id,
        'status'         => LoanStatus::Submitted,
        'principal_amount' => 3000,
        'tenure'         => 6,
    ]);
}

function approvedLoan(User $admin): Loan
{
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    return Loan::factory()->create([
        'borrower_id'    => $borrower->id,
        'loan_type_id'   => $type->id,
        'loan_plan_id'   => $plan->id,
        'created_by'     => $admin->id,
        'status'         => LoanStatus::Approved,
        'principal_amount' => 3000,
        'tenure'         => 6,
    ]);
}

// ─── Borrower CSV Import ──────────────────────────────────────────────────────

test('can import borrowers from valid csv', function () {
    $admin = bulkAdmin();

    $csv = "first_name,last_name,phone,email\n";
    $csv .= "Alice,Banda,+260971000001,alice@example.com\n";
    $csv .= "Bob,Mwale,+260971000002,bob@example.com\n";

    $file = csvFile($csv);

    $response = $this->actingAs($admin)
        ->postJson(route('bulk.import-borrowers.upload'), ['file' => $file])
        ->assertOk();

    expect($response->json('results.imported'))->toBe(2);
    expect($response->json('results.skipped'))->toBe(0);

    $this->assertDatabaseHas('borrowers', ['phone' => '+260971000001']);
    $this->assertDatabaseHas('borrowers', ['phone' => '+260971000002']);
})->group('bulk');

test('import skips rows with duplicate phone numbers', function () {
    $admin = bulkAdmin();

    Borrower::factory()->create(['phone' => '+260971000010']);

    $csv = "first_name,last_name,phone\n";
    $csv .= "Alice,Banda,+260971000010\n";   // duplicate
    $csv .= "Bob,Mwale,+260971000011\n";     // new

    $file = csvFile($csv);

    $response = $this->actingAs($admin)
        ->postJson(route('bulk.import-borrowers.upload'), ['file' => $file])
        ->assertOk();

    expect($response->json('results.imported'))->toBe(1);
    expect($response->json('results.skipped'))->toBe(1);
    expect($response->json('results.errors'))->not->toBeEmpty();
})->group('bulk');

test('import skips rows missing required first_name or phone', function () {
    $admin = bulkAdmin();

    $csv = "first_name,last_name,phone\n";
    $csv .= ",Banda,+260971000020\n";        // missing first_name
    $csv .= "Alice,,\n";                     // missing phone

    $file = csvFile($csv);

    $response = $this->actingAs($admin)
        ->postJson(route('bulk.import-borrowers.upload'), ['file' => $file])
        ->assertOk();

    expect($response->json('results.imported'))->toBe(0);
    expect($response->json('results.skipped'))->toBe(2);
})->group('bulk');

test('import rejects csv missing first_name header', function () {
    $admin = bulkAdmin();

    $csv = "name,phone\nAlice,+260971000030\n";
    $file = csvFile($csv);

    $this->actingAs($admin)
        ->postJson(route('bulk.import-borrowers.upload'), ['file' => $file])
        ->assertUnprocessable();
})->group('bulk');

test('import rejects non-csv file', function () {
    $admin = bulkAdmin();

    $file = UploadedFile::fake()->create('import.pdf', 100, 'application/pdf');

    $this->actingAs($admin)
        ->postJson(route('bulk.import-borrowers.upload'), ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
})->group('bulk');

test('import requires file upload', function () {
    $admin = bulkAdmin();

    $this->actingAs($admin)
        ->postJson(route('bulk.import-borrowers.upload'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
})->group('bulk');

// ─── Bulk Loan Approve ────────────────────────────────────────────────────────

test('can bulk approve submitted loans', function () {
    $admin = bulkAdmin();
    $loan1 = submittedLoan($admin);
    $loan2 = submittedLoan($admin);

    $this->actingAs($admin)
        ->postJson(route('bulk.loans.approve'), [
            'loan_ids' => [$loan1->id, $loan2->id],
        ])
        ->assertOk()
        ->assertJsonPath('approved', 2);

    expect($loan1->fresh()->status)->toBe(LoanStatus::Approved);
    expect($loan2->fresh()->status)->toBe(LoanStatus::Approved);
})->group('bulk');

test('bulk approve skips loans not in submitted status', function () {
    $admin = bulkAdmin();
    $loan1 = submittedLoan($admin);
    $loan2 = approvedLoan($admin);  // already approved

    $response = $this->actingAs($admin)
        ->postJson(route('bulk.loans.approve'), [
            'loan_ids' => [$loan1->id, $loan2->id],
        ])
        ->assertOk();

    expect($response->json('approved'))->toBe(1);
    expect($response->json('errors'))->not->toBeEmpty();
})->group('bulk');

test('bulk approve requires loan_ids array', function () {
    $admin = bulkAdmin();

    $this->actingAs($admin)
        ->postJson(route('bulk.loans.approve'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['loan_ids']);
})->group('bulk');

test('bulk approve rejects empty loan_ids array', function () {
    $admin = bulkAdmin();

    $this->actingAs($admin)
        ->postJson(route('bulk.loans.approve'), ['loan_ids' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['loan_ids']);
})->group('bulk');

// ─── Bulk Loan Disburse ───────────────────────────────────────────────────────

test('can bulk disburse approved loans', function () {
    $admin = bulkAdmin();
    $loan1 = approvedLoan($admin);
    $loan2 = approvedLoan($admin);

    $this->actingAs($admin)
        ->postJson(route('bulk.loans.disburse'), [
            'loan_ids'            => [$loan1->id, $loan2->id],
            'disbursement_method' => 'bank_transfer',
            'disbursement_date'   => now()->toDateString(),
        ])
        ->assertOk()
        ->assertJsonPath('disbursed', 2);

    expect($loan1->fresh()->status)->toBe(LoanStatus::Active);
    expect($loan2->fresh()->status)->toBe(LoanStatus::Active);
})->group('bulk');

test('bulk disburse skips loans not in approved status', function () {
    $admin = bulkAdmin();
    $loan1 = approvedLoan($admin);
    $loan2 = submittedLoan($admin);  // not approved yet

    $response = $this->actingAs($admin)
        ->postJson(route('bulk.loans.disburse'), [
            'loan_ids'            => [$loan1->id, $loan2->id],
            'disbursement_method' => 'cash',
            'disbursement_date'   => now()->toDateString(),
        ])
        ->assertOk();

    expect($response->json('disbursed'))->toBe(1);
})->group('bulk');

test('bulk disburse requires disbursement_method and date', function () {
    $admin = bulkAdmin();
    $loan  = approvedLoan($admin);

    $this->actingAs($admin)
        ->postJson(route('bulk.loans.disburse'), [
            'loan_ids' => [$loan->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['disbursement_method', 'disbursement_date']);
})->group('bulk');

// ─── Batch Payment Upload ─────────────────────────────────────────────────────

test('can process batch payments from csv', function () {
    $admin = bulkAdmin();

    // Use factory active() state which sets all required disbursement fields
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();
    $loan     = Loan::factory()->active()->create([
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'created_by'   => $admin->id,
    ]);

    $csv  = "loan_number,amount,payment_date,payment_method\n";
    $csv .= "{$loan->loan_number},500," . now()->toDateString() . ",cash\n";

    $file = csvFile($csv, 'payments.csv');

    $response = $this->actingAs($admin)
        ->postJson(route('bulk.payments.upload'), ['file' => $file])
        ->assertOk();

    expect($response->json('results.processed'))->toBe(1);
    expect($response->json('results.skipped'))->toBe(0);
})->group('bulk');

test('batch payments skips unknown loan numbers', function () {
    $admin = bulkAdmin();

    $csv  = "loan_number,amount\n";
    $csv .= "LN-FAKE-99999,500\n";

    $file = csvFile($csv, 'payments.csv');

    $response = $this->actingAs($admin)
        ->postJson(route('bulk.payments.upload'), ['file' => $file])
        ->assertOk();

    expect($response->json('results.skipped'))->toBe(1);
    expect($response->json('results.errors'))->not->toBeEmpty();
})->group('bulk');

test('batch payments skips non-active loans', function () {
    $admin    = bulkAdmin();
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();
    $loan     = Loan::factory()->create([
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'created_by'   => $admin->id,
        'status'       => LoanStatus::Submitted,
    ]);

    $csv  = "loan_number,amount\n";
    $csv .= "{$loan->loan_number},500\n";

    $file = csvFile($csv, 'payments.csv');

    $response = $this->actingAs($admin)
        ->postJson(route('bulk.payments.upload'), ['file' => $file])
        ->assertOk();

    expect($response->json('results.skipped'))->toBe(1);
})->group('bulk');

test('batch payments rejects csv missing loan_number header', function () {
    $admin = bulkAdmin();

    $csv  = "ref,amount\nLN-001,500\n";
    $file = csvFile($csv, 'payments.csv');

    $this->actingAs($admin)
        ->postJson(route('bulk.payments.upload'), ['file' => $file])
        ->assertUnprocessable();
})->group('bulk');

// ─── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated user cannot access bulk import', function () {
    $csv  = "first_name,phone\nAlice,+260971000099\n";
    $file = csvFile($csv);

    // Bulk routes use web/session auth — JSON requests get 401, browser requests get redirect
    $response = $this->postJson(route('bulk.import-borrowers.upload'), ['file' => $file]);
    expect($response->status())->toBeIn([401, 302]);
})->group('bulk');

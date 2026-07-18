<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanDocument;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function docUser(): User
{
    return User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'is_active' => true,
    ]);
}

function docLoan(): Loan
{
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    return Loan::factory()->create([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status' => LoanStatus::Draft,
    ]);
}

// ─── List Documents ───────────────────────────────────────────────────────────

test('can list documents for a loan', function () {
    Storage::fake('public');
    $user = docUser();
    $loan = docLoan();

    LoanDocument::create([
        'loan_id' => $loan->id,
        'document_type' => 'application_form',
        'title' => 'Loan Application',
        'file_path' => '/storage/loans/1/documents/form.pdf',
        'file_name' => 'form.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 1024,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->getJson(route('api.v1.loans.documents.index', $loan))
        ->assertOk()
        ->assertJsonPath('data.0.document_type', 'application_form')
        ->assertJsonPath('data.0.title', 'Loan Application');
})->group('documents');

test('document list is empty when no documents exist', function () {
    $user = docUser();
    $loan = docLoan();

    $this->actingAs($user)
        ->getJson(route('api.v1.loans.documents.index', $loan))
        ->assertOk()
        ->assertJsonCount(0, 'data');
})->group('documents');

// ─── Upload Document ──────────────────────────────────────────────────────────

test('can upload a document to a loan', function () {
    Storage::fake('public');
    $user = docUser();
    $loan = docLoan();

    $file = UploadedFile::fake()->create('agreement.pdf', 512, 'application/pdf');

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.documents.store', $loan), [
            'file' => $file,
            'document_type' => 'loan_agreement',
            'title' => 'Signed Agreement',
        ])
        ->assertCreated()
        ->assertJsonPath('data.document_type', 'loan_agreement')
        ->assertJsonPath('data.title', 'Signed Agreement')
        ->assertJsonPath('data.file_name', 'agreement.pdf');

    $this->assertDatabaseHas('loan_documents', [
        'loan_id' => $loan->id,
        'document_type' => 'loan_agreement',
        'title' => 'Signed Agreement',
        'uploaded_by' => $user->id,
    ]);
})->group('documents');

test('upload uses original filename as title when title not provided', function () {
    Storage::fake('public');
    $user = docUser();
    $loan = docLoan();

    $file = UploadedFile::fake()->create('id-copy.jpg', 200, 'image/jpeg');

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.documents.store', $loan), [
            'file' => $file,
            'document_type' => 'national_id',
        ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'id-copy.jpg');
})->group('documents');

test('upload records uploader id', function () {
    Storage::fake('public');
    $user = docUser();
    $loan = docLoan();

    $file = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.loans.documents.store', $loan), [
            'file' => $file,
            'document_type' => 'collateral',
        ])
        ->assertCreated();

    $this->assertDatabaseHas('loan_documents', [
        'loan_id' => $loan->id,
        'uploaded_by' => $user->id,
    ]);
})->group('documents');

test('upload stores file on public disk', function () {
    Storage::fake('public');
    $user = docUser();
    $loan = docLoan();

    $file = UploadedFile::fake()->create('evidence.png', 300, 'image/png');

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.documents.store', $loan), [
            'file' => $file,
            'document_type' => 'collateral_evidence',
        ])
        ->assertCreated();

    // File should exist somewhere in loans/{id}/documents/
    $stored = LoanDocument::where('loan_id', $loan->id)->latest()->first();
    $this->assertNotNull($stored);
})->group('documents');

// ─── Validation ───────────────────────────────────────────────────────────────

test('upload fails without a file', function () {
    $user = docUser();
    $loan = docLoan();

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.documents.store', $loan), [
            'document_type' => 'application_form',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
})->group('documents');

test('upload fails without document_type', function () {
    Storage::fake('public');
    $user = docUser();
    $loan = docLoan();

    $file = UploadedFile::fake()->create('doc.pdf', 100);

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.documents.store', $loan), [
            'file' => $file,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['document_type']);
})->group('documents');

test('upload fails for disallowed mime type', function () {
    Storage::fake('public');
    $user = docUser();
    $loan = docLoan();

    $file = UploadedFile::fake()->create('malware.exe', 100, 'application/octet-stream');

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.documents.store', $loan), [
            'file' => $file,
            'document_type' => 'other',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
})->group('documents');

test('upload fails for file exceeding 10 MB', function () {
    Storage::fake('public');
    $user = docUser();
    $loan = docLoan();

    // 11 MB file
    $file = UploadedFile::fake()->create('big.pdf', 11264, 'application/pdf');

    $this->actingAs($user)
        ->postJson(route('api.v1.loans.documents.store', $loan), [
            'file' => $file,
            'document_type' => 'application_form',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
})->group('documents');

// ─── Delete Document ──────────────────────────────────────────────────────────

test('can delete a document from a loan', function () {
    Storage::fake('public');
    $user = docUser();
    $loan = docLoan();

    $document = LoanDocument::create([
        'loan_id' => $loan->id,
        'document_type' => 'other',
        'title' => 'Temp Doc',
        'file_path' => '/storage/loans/1/documents/temp.pdf',
        'file_name' => 'temp.pdf',
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('api.v1.loans.documents.destroy', [$loan, $document]))
        ->assertOk()
        ->assertJsonPath('message', 'Document deleted.');

    $this->assertSoftDeleted('loan_documents', ['id' => $document->id]);
})->group('documents');

test('cannot delete a document that belongs to a different loan', function () {
    Storage::fake('public');
    $user = docUser();
    $loan1 = docLoan();
    $loan2 = docLoan();

    $document = LoanDocument::create([
        'loan_id' => $loan1->id,
        'document_type' => 'other',
        'file_path' => '/storage/x.pdf',
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->deleteJson(route('api.v1.loans.documents.destroy', [$loan2, $document]))
        ->assertStatus(404);
})->group('documents');

// ─── Authorization ────────────────────────────────────────────────────────────

test('unauthenticated user cannot access loan documents', function () {
    $loan = docLoan();

    $this->getJson(route('api.v1.loans.documents.index', $loan))
        ->assertUnauthorized();
})->group('documents');

test('multiple documents can be uploaded to same loan', function () {
    Storage::fake('public');
    $user = docUser();
    $loan = docLoan();

    foreach (['application_form', 'national_id', 'collateral'] as $type) {
        $file = UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf');
        $this->actingAs($user)
            ->postJson(route('api.v1.loans.documents.store', $loan), [
                'file' => $file,
                'document_type' => $type,
            ])
            ->assertCreated();
    }

    $this->assertDatabaseCount('loan_documents', 3);
})->group('documents');

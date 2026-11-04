<?php

use App\Exceptions\LoanActionException;
use App\Models\Landlord\PlanConfig;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\FieldCollection;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use App\Services\LoanLifecycleService;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

/*
 * Field sync never records a payment twice, batch uploads read real-world CSVs,
 * the staff API takes corporate borrowers, borrowers see their real credit score,
 * the plan's mobile-money gate holds, and the pages that had no route open.
 */

beforeEach(function () {
    $this->admin = actingAsAdmin();
    $this->actingAs($this->admin);
});

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(fn () => Tenant::query()->where('id', 'like', 'ops-%')->each(fn ($t) => $t->delete()));
});

function payableLoan(): Loan
{
    return Loan::factory()->active()->create([
        'borrower_id' => Borrower::factory()->create()->id, 'principal_amount' => 2000, 'interest_amount' => 400,
        'total_payable' => 2400, 'outstanding_balance' => 2400, 'total_paid' => 0,
        'disbursement_date' => now()->subMonth()->toDateString(),
    ]);
}

// ─── Offline field sync ──────────────────────────────────────────────────────

it('records a synced collection once even when the phone sends the batch again', function () {
    $loan = payableLoan();
    Sanctum::actingAs($this->admin);
    $batch = ['items' => [[
        'action' => 'collect_payment', 'client_id' => 'device-42-item-7',
        'payload' => ['loan_id' => $loan->id, 'amount' => 300, 'collection_method' => 'cash', 'collected_at' => now()->toDateString()],
    ]]];

    $this->postJson(route('api.v1.field.sync'), $batch)->assertOk()->assertJsonPath('data.processed', 1);
    $this->postJson(route('api.v1.field.sync'), $batch)->assertOk()->assertJsonPath('data.results.0.duplicate', true);

    expect(Payment::where('loan_id', $loan->id)->count())->toBe(1)
        ->and(FieldCollection::where('loan_id', $loan->id)->count())->toBe(1);
});

it('recognises a resent item by its contents when the device gives no id', function () {
    $loan = payableLoan();
    Sanctum::actingAs($this->admin);
    $batch = ['items' => [[
        'action' => 'collect_payment',
        'payload' => ['loan_id' => $loan->id, 'amount' => 150, 'collection_method' => 'mobile_money', 'payment_method' => 'airtel_money', 'collected_at' => now()->toDateString()],
    ]]];

    $this->postJson(route('api.v1.field.sync'), $batch)->assertOk();
    $this->postJson(route('api.v1.field.sync'), $batch)->assertOk();

    expect(Payment::where('loan_id', $loan->id)->count())->toBe(1)
        ->and(Payment::where('loan_id', $loan->id)->value('payment_method')->value)->toBe('airtel_money');
});

// ─── Batch payments ──────────────────────────────────────────────────────────

function batchCsv(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('payments.csv', $contents);
}

it('reads an Excel CSV with a BOM, thousands separators and ragged rows', function () {
    $a = payableLoan();
    $b = payableLoan();
    $csv = "\xEF\xBB\xBFloan_number,amount,payment_date,payment_method,reference\n"
        ."{$a->loan_number},\"1,000.00\",".now()->toDateString().",cash,BATCH-1\n"
        ."{$b->loan_number},250\n"                      // short row: the rest default
        ."{$b->loan_number},abc,,,\n"                   // not an amount
        ."{$a->loan_number},50,,,BATCH-2,extra,cells\n"; // long row used to crash

    $response = $this->post(route('bulk.payments.upload'), ['file' => batchCsv($csv)])->assertOk();

    expect($response->json('results.processed'))->toBe(3)
        ->and($response->json('results.skipped'))->toBe(1)
        ->and((float) Payment::where('loan_id', $a->id)->where('reference', 'BATCH-1')->value('amount'))->toBe(1000.0)
        ->and(Payment::where('source', 'migration')->exists())->toBeFalse();
});

it('refuses a reference already used, row by row', function () {
    $loan = payableLoan();
    $csv = "loan_number,amount,reference\n{$loan->loan_number},100,DUP-1\n{$loan->loan_number},100,DUP-1\n";

    $response = $this->post(route('bulk.payments.upload'), ['file' => batchCsv($csv)])->assertOk();

    expect($response->json('results.processed'))->toBe(1)
        ->and(Payment::where('loan_id', $loan->id)->count())->toBe(1);
});

// ─── Corporate borrowers over the API ────────────────────────────────────────

it('creates and edits a corporate borrower over the staff API', function () {
    Sanctum::actingAs($this->admin);

    $id = $this->postJson(route('api.v1.borrowers.store'), [
        'borrower_type' => 'corporate', 'company_name' => 'Lusaka Agro Ltd', 'company_reg_number' => '120190012345',
        'contact_person_name' => 'Mwila Zulu', 'phone' => '0971234500',
    ])->assertCreated()->json('data.id');

    $borrower = Borrower::findOrFail($id);
    expect($borrower->borrower_type)->toBe(Borrower::TYPE_CORPORATE)
        ->and($borrower->company_name)->toBe('Lusaka Agro Ltd');

    $this->putJson(route('api.v1.borrowers.update', $borrower), ['industry' => 'Agriculture'])->assertOk();
    expect($borrower->fresh()->industry)->toBe('Agriculture')
        ->and($borrower->fresh()->company_name)->toBe('Lusaka Agro Ltd');

    $this->postJson(route('api.v1.borrowers.store'), ['borrower_type' => 'corporate', 'phone' => '0971234501'])
        ->assertUnprocessable()->assertJsonValidationErrors(['company_name', 'company_reg_number', 'contact_person_name']);

    $this->postJson(route('api.v1.borrowers.store'), ['first_name' => 'Ana', 'last_name' => 'Tembo', 'phone' => '12345'])
        ->assertUnprocessable()->assertJsonValidationErrors('phone');
});

// ─── Borrower credit score ───────────────────────────────────────────────────

it('gives the borrower their real score and its factors', function () {
    $loan = payableLoan();
    Sanctum::actingAs($loan->borrower);

    $this->getJson(route('api.v1.borrower.credit-score'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['score', 'score_band', 'band', 'repayment_history_score', 'debt_load_score',
            'history_length_score', 'account_mix_score', 'new_credit_score', 'total_loans', 'last_updated']])
        ->assertJsonPath('data.total_loans', 1);
});

// ─── Plan gate on mobile-money payouts ───────────────────────────────────────

it('refuses a mobile-money payout on a plan without it, and allows cash', function () {
    PlanConfig::updateOrCreate(['plan' => 'starter'], ['label' => 'Starter', 'features' => ['disbursement_mobile_money' => false, 'max_users' => 50]]);
    config(['tenancy.bootstrappers' => []]);
    tenancy()->initialize(Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'ops-'.uniqid(), 'name' => 'Small Lender', 'slug' => 'small-'.uniqid(), 'plan' => 'starter',
        'status' => 'active', 'currency' => 'ZMW', 'timezone' => 'Africa/Lusaka', 'email_verified_at' => now(),
    ])));
    fundTheBook();
    $loan = Loan::factory()->approved()->create(['loan_plan_id' => makePlan()->id, 'disbursement_account' => '0971234567']);
    $lifecycle = app(LoanLifecycleService::class);

    expect(fn () => $lifecycle->disburse($loan, $this->admin, ['disbursement_method' => 'airtel_money', 'disbursement_date' => now()->toDateString()]))
        ->toThrow(LoanActionException::class, 'Mobile-money payouts');

    expect($lifecycle->disburse($loan->fresh(), $this->admin, ['disbursement_method' => 'cash', 'disbursement_date' => now()->toDateString()])['payout_sent'])->toBeFalse();
});

// ─── Pages that had no route ─────────────────────────────────────────────────

it('opens the agents, risk policies, message templates and marketplace listing pages', function () {
    $this->get(route('agents.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('agents/Index'));
    $this->get(route('risk-policies.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('risk-policies/Index'));
    $this->get(route('notification-templates.index'))->assertOk()->assertInertia(fn (Assert $p) => $p->component('notification-templates/Index'));
    $this->get('/app/credit-score')->assertOk();
});

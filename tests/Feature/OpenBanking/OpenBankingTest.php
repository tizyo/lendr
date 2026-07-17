<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\ApiAccessLog;
use App\Models\Tenant\ApiClient;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Hash;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function openBankingAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function makeApiClient(array $attrs = []): array
{
    $rawKey = $attrs['client_key'] ?? 'lndr_' . str_repeat('x', 40);

    $client = ApiClient::create(array_merge([
        'name'                  => 'Test Client',
        'client_key'            => $rawKey,
        'client_secret'         => Hash::make($rawKey),
        'scopes'                => ['products_read', 'loan_apply', 'loan_status', 'payment_initiate'],
        'is_active'             => true,
        'rate_limit_per_minute' => 60,
    ], $attrs));

    return ['client' => $client, 'key' => $rawKey];
}

function apiHeaders(string $key): array
{
    return ['X-API-Key' => $key];
}

// ─── API Client Management (Admin) ────────────────────────────────────────────

test('admin can create an API client', function () {
    $admin = openBankingAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.api-clients.store'), [
            'name'   => 'Partner Integration',
            'scopes' => ['products_read', 'loan_status'],
        ])
        ->assertStatus(201);

    expect($resp->json('data.client.name'))->toBe('Partner Integration')
        ->and($resp->json('data.client.client_key'))->toStartWith('lndr_');
});

test('admin can list API clients', function () {
    $admin = openBankingAdmin();
    makeApiClient(['name' => 'Client A', 'client_key' => 'lndr_' . str_repeat('a', 40)]);
    makeApiClient(['name' => 'Client B', 'client_key' => 'lndr_' . str_repeat('b', 40)]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.api-clients.index'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(2);
});

test('admin can rotate API key', function () {
    $admin  = openBankingAdmin();
    $data   = makeApiClient();
    $client = $data['client'];

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.api-clients.rotate-key', $client))
        ->assertOk();

    $newKey = $resp->json('data.client_key');
    expect($newKey)->toStartWith('lndr_')
        ->and($newKey)->not->toBe($data['key']);
});

test('admin can deactivate an API client', function () {
    $admin  = openBankingAdmin();
    $data   = makeApiClient();
    $client = $data['client'];

    $this->actingAs($admin)
        ->putJson(route('api.v1.api-clients.update', $client), ['is_active' => false])
        ->assertOk();

    expect(ApiClient::find($client->id)->is_active)->toBeFalse();
});

test('unauthenticated cannot manage API clients', function () {
    $this->getJson(route('api.v1.api-clients.index'))->assertUnauthorized();
    $this->postJson(route('api.v1.api-clients.store'))->assertUnauthorized();
});

// ─── Open Banking Endpoints ────────────────────────────────────────────────────

test('open API requires X-API-Key header', function () {
    $this->getJson('/api/v1/open/v1/products')
        ->assertStatus(401);
});

test('open API rejects invalid API key', function () {
    $this->withHeaders(['X-API-Key' => 'invalid_key_xyz'])
        ->getJson('/api/v1/open/v1/products')
        ->assertStatus(401);
});

test('open API rejects inactive client', function () {
    $data = makeApiClient(['is_active' => false, 'client_key' => 'lndr_' . str_repeat('i', 40)]);

    $this->withHeaders(apiHeaders($data['key']))
        ->getJson('/api/v1/open/v1/products')
        ->assertStatus(401);
});

test('can get loan products with valid API key', function () {
    $data = makeApiClient();
    $type = LoanType::factory()->create(['is_active' => true]);
    LoanPlan::factory()->create(['loan_type_id' => $type->id, 'is_active' => true]);

    $resp = $this->withHeaders(apiHeaders($data['key']))
        ->getJson('/api/v1/open/v1/products')
        ->assertOk();

    expect($resp->json('data.products'))->not->toBeEmpty();
});

test('can submit a loan application via open API', function () {
    $data = makeApiClient();
    $type = LoanType::factory()->create(['is_active' => true]);
    $plan = LoanPlan::factory()->create([
        'loan_type_id'   => $type->id,
        'is_active'      => true,
        'interest_rate'  => 24,
        'interest_type'  => 'flat',
        'interest_period' => 'monthly',
    ]);

    $resp = $this->withHeaders(apiHeaders($data['key']))
        ->postJson('/api/v1/open/v1/loan/apply', [
            'first_name'   => 'John',
            'phone'        => '+260977000001',
            'loan_plan_id' => $plan->id,
            'amount'       => 5000,
            'tenure'       => 6,
        ])
        ->assertStatus(201);

    expect($resp->json('data.reference'))->toStartWith('LN-EXT-')
        ->and($resp->json('data.status'))->toBe('submitted');
});

test('loan application creates or reuses borrower by phone', function () {
    $data = makeApiClient();
    $type = LoanType::factory()->create(['is_active' => true]);
    $plan = LoanPlan::factory()->create([
        'loan_type_id'   => $type->id,
        'is_active'      => true,
        'interest_rate'  => 24,
        'interest_type'  => 'flat',
        'interest_period' => 'monthly',
    ]);

    $phone = '+260977999888';

    // First application
    $this->withHeaders(apiHeaders($data['key']))->postJson('/api/v1/open/v1/loan/apply', [
        'first_name' => 'Alice', 'phone' => $phone, 'loan_plan_id' => $plan->id, 'amount' => 3000, 'tenure' => 3,
    ]);

    // Second application — same phone
    $this->withHeaders(apiHeaders($data['key']))->postJson('/api/v1/open/v1/loan/apply', [
        'first_name' => 'Alice', 'phone' => $phone, 'loan_plan_id' => $plan->id, 'amount' => 2000, 'tenure' => 2,
    ]);

    expect(Borrower::where('phone', $phone)->count())->toBe(1);
    expect(Loan::where('outstanding_balance', 3000)->count())->toBe(1);
});

test('can check loan status by reference', function () {
    $data = makeApiClient();
    $type = LoanType::factory()->create(['is_active' => true]);
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id, 'is_active' => true]);
    $borrower = Borrower::factory()->create();

    $loan = Loan::factory()->create([
        'borrower_id'      => $borrower->id,
        'loan_type_id'     => $type->id,
        'loan_plan_id'     => $plan->id,
        'loan_number'      => 'LN-EXT-TESTREF',
        'status'           => LoanStatus::Active,
        'principal_amount' => 10000,
        'outstanding_balance' => 8000,
    ]);

    $resp = $this->withHeaders(apiHeaders($data['key']))
        ->getJson('/api/v1/open/v1/loan/LN-EXT-TESTREF/status')
        ->assertOk();

    expect($resp->json('data.reference'))->toBe('LN-EXT-TESTREF')
        ->and($resp->json('data.status'))->toBe('active');
});

test('loan status returns 404 for unknown reference', function () {
    $data = makeApiClient();

    $this->withHeaders(apiHeaders($data['key']))
        ->getJson('/api/v1/open/v1/loan/UNKNOWN-REF/status')
        ->assertStatus(404);
});

test('can initiate a payment via open API', function () {
    $data     = makeApiClient();
    $type     = LoanType::factory()->create(['is_active' => true]);
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id, 'is_active' => true]);
    $borrower = Borrower::factory()->create();

    Loan::factory()->create([
        'borrower_id'         => $borrower->id,
        'loan_type_id'        => $type->id,
        'loan_plan_id'        => $plan->id,
        'loan_number'         => 'LN-EXT-PAYTEST',
        'status'              => LoanStatus::Active,
        'outstanding_balance' => 5000,
    ]);

    $resp = $this->withHeaders(apiHeaders($data['key']))
        ->postJson('/api/v1/open/v1/payment/initiate', [
            'loan_reference' => 'LN-EXT-PAYTEST',
            'amount'         => 500,
            'phone'          => '+260977111222',
        ])
        ->assertStatus(201);

    expect($resp->json('data.payment_reference'))->toStartWith('PAY-EXT-')
        ->and($resp->json('data.status'))->toBe('pending');
});

test('API access is logged after each request', function () {
    $data = makeApiClient();

    $this->withHeaders(apiHeaders($data['key']))
        ->getJson('/api/v1/open/v1/products')
        ->assertOk();

    expect(ApiAccessLog::where('api_client_id', $data['client']->id)->count())->toBe(1);
});

test('rate limit returns 429 when exceeded', function () {
    $data = makeApiClient(['rate_limit_per_minute' => 1, 'client_key' => 'lndr_' . str_repeat('r', 40)]);

    // First request — ok
    $this->withHeaders(apiHeaders($data['key']))->getJson('/api/v1/open/v1/products')->assertOk();

    // Second request — rate limited
    $this->withHeaders(apiHeaders($data['key']))->getJson('/api/v1/open/v1/products')->assertStatus(429);
});

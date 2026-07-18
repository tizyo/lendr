<?php

use App\Enums\UserRole;
use App\Models\Landlord\PublicLoanProduct;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function mktAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function mktBorrower(): Borrower
{
    return Borrower::factory()->create(['is_active' => true]);
}

function mktLoanType(): LoanType
{
    return LoanType::create(['name' => 'Mkt Personal', 'code' => 'MKT-'.uniqid(), 'is_active' => true]);
}

function mktPlan(LoanType $lt): LoanPlan
{
    return LoanPlan::create([
        'loan_type_id' => $lt->id,
        'name' => 'Basic Plan',
        'code' => 'BP-'.uniqid(),
        'interest_rate' => 18,
        'interest_type' => 'flat',
        'interest_period' => 'monthly',
        'min_tenure' => 1,
        'max_tenure' => 12,
        'tenure_type' => 'months',
        'min_amount' => 1000,
        'max_amount' => 50000,
        'penalty_rate' => 2,
        'penalty_type' => 'flat',
        'grace_period_days' => 0,
        'repayment_schedule' => 'monthly',
        'processing_fee' => 2,
        'insurance_fee' => 0,
        'is_active' => true,
    ]);
}

function mktProduct(array $extra = []): PublicLoanProduct
{
    return PublicLoanProduct::create(array_merge([
        'tenant_id' => 'tenant-test-'.uniqid(),
        'tenant_name' => 'Test MFI',
        'product_name' => 'Quick Loan',
        'min_amount' => 1000,
        'max_amount' => 50000,
        'interest_rate' => 18,
        'interest_type' => 'flat',
        'interest_period' => 'monthly',
        'min_tenure' => 1,
        'max_tenure' => 12,
        'tenure_type' => 'months',
        'repayment_schedule' => 'monthly',
        'processing_fee' => 2,
        'is_active' => true,
    ], $extra));
}

// ─── API endpoint tests ───────────────────────────────────────────────────────

test('GET marketplace/products lists active products', function () {
    $admin = mktAdmin();
    mktProduct();
    mktProduct(['is_active' => false]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.marketplace.browse'))
        ->assertOk();

    $products = $resp->json('data');
    expect(count($products))->toBe(1);   // only active
});

test('GET marketplace/products/{id} returns single product', function () {
    $admin = mktAdmin();
    $product = mktProduct();

    $this->actingAs($admin)
        ->getJson(route('api.v1.marketplace.show', $product->id))
        ->assertOk()
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.product_name', 'Quick Loan');
});

test('GET marketplace/products/{id} returns 404 for inactive product', function () {
    $admin = mktAdmin();
    $product = mktProduct(['is_active' => false]);

    $this->actingAs($admin)
        ->getJson(route('api.v1.marketplace.show', $product->id))
        ->assertStatus(404);
});

test('POST marketplace/products publishes a loan type to marketplace', function () {
    $admin = mktAdmin();
    $lt = mktLoanType();
    mktPlan($lt);

    $this->actingAs($admin)
        ->postJson(route('api.v1.marketplace.publish'), [
            'loan_type_id' => $lt->id,
            'product_name' => 'My Published Product',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.product_name', 'My Published Product');

    expect(PublicLoanProduct::where('product_name', 'My Published Product')->exists())->toBeTrue();
});

test('POST marketplace/products returns 422 when loan type has no active plan', function () {
    $admin = mktAdmin();
    $lt = mktLoanType();
    // No plan created

    $this->actingAs($admin)
        ->postJson(route('api.v1.marketplace.publish'), [
            'loan_type_id' => $lt->id,
            'product_name' => 'No Plan Product',
        ])
        ->assertStatus(422);
});

test('DELETE marketplace/products/{id} deactivates listing', function () {
    $admin = mktAdmin();
    $product = mktProduct(['tenant_id' => 'local']);

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.marketplace.unpublish', $product->id))
        ->assertOk();

    expect(PublicLoanProduct::find($product->id)->is_active)->toBeFalse();
});

test('GET marketplace/my-products returns tenant products', function () {
    $admin = mktAdmin();
    mktProduct(['tenant_id' => 'local']);
    mktProduct(['tenant_id' => 'other-tenant']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.marketplace.my-products'))
        ->assertOk();

    // In test env tenant('id') = null → 'local', so only 1 local product returned
    expect(count($resp->json('data')))->toBe(1);
});

test('GET marketplace/products filters by amount range', function () {
    $admin = mktAdmin();
    mktProduct(['min_amount' => 1000, 'max_amount' => 10000]);
    mktProduct(['min_amount' => 50000, 'max_amount' => 200000]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.marketplace.browse').'?max_amount=15000')
        ->assertOk();

    expect(count($resp->json('data')))->toBe(1);
});

test('unauthenticated cannot access marketplace management', function () {
    $this->postJson(route('api.v1.marketplace.publish'), [])
        ->assertStatus(401);
});

// ─── Keyword search ───────────────────────────────────────────────────────────

test('GET marketplace/products filters by keyword q', function () {
    $admin = mktAdmin();
    mktProduct(['product_name' => 'Agricultural Loan']);
    mktProduct(['product_name' => 'Business Loan']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.marketplace.browse').'?q=Agri')
        ->assertOk();

    expect(count($resp->json('data')))->toBe(1)
        ->and($resp->json('data.0.product_name'))->toBe('Agricultural Loan');
});

test('GET marketplace/products keyword search matches tenant name', function () {
    $admin = mktAdmin();
    mktProduct(['tenant_name' => 'Zambia Micro Finance', 'product_name' => 'Quick Cash']);
    mktProduct(['tenant_name' => 'Lusaka Savings', 'product_name' => 'Quick Cash']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.marketplace.browse').'?q=Zambia')
        ->assertOk();

    expect(count($resp->json('data')))->toBe(1);
});

// ─── Borrower portal public products ─────────────────────────────────────────

test('GET me/public-products returns active products for borrower', function () {
    $borrower = mktBorrower();   // reuse helper from PushNotification tests
    mktProduct(['is_active' => true]);
    mktProduct(['is_active' => false]);

    $token = $borrower->createToken('portal')->plainTextToken;

    $resp = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.v1.borrower.public-products.browse'))
        ->assertOk();

    expect(count($resp->json('data')))->toBe(1);
});

test('GET me/public-products supports keyword search', function () {
    $borrower = mktBorrower();
    mktProduct(['product_name' => 'Micro Business Loan']);
    mktProduct(['product_name' => 'Personal Credit']);

    $token = $borrower->createToken('portal')->plainTextToken;

    $resp = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson(route('api.v1.borrower.public-products.browse').'?q=Micro')
        ->assertOk();

    expect(count($resp->json('data')))->toBe(1)
        ->and($resp->json('data.0.product_name'))->toBe('Micro Business Loan');
});

test('POST me/public-products/{id}/apply increments applications_count', function () {
    $borrower = mktBorrower();
    $product = mktProduct(['applications_count' => 5]);

    $token = $borrower->createToken('portal')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.v1.borrower.public-products.apply', $product->id))
        ->assertOk()
        ->assertJsonPath('data.product.applications_count', 6);

    expect(\App\Models\Landlord\PublicLoanProduct::find($product->id)->applications_count)->toBe(6);
});

test('POST me/public-products/{id}/apply returns 404 for inactive product', function () {
    $borrower = mktBorrower();
    $product = mktProduct(['is_active' => false]);

    $token = $borrower->createToken('portal')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson(route('api.v1.borrower.public-products.apply', $product->id))
        ->assertStatus(404);
});

test('unauthenticated cannot browse borrower public products', function () {
    $this->getJson(route('api.v1.borrower.public-products.browse'))
        ->assertStatus(401);
});

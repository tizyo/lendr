<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\FieldCheckIn;
use App\Models\Tenant\FieldCollection;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\OfflineSyncItem;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function fieldOfficer(): User
{
    return User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
}

function fieldLoan(): Loan
{
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create(['phone' => '0971000001']);

    return Loan::factory()->create([
        'borrower_id'  => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status'       => LoanStatus::Active,
        'loan_number'  => 'LN-FIELD-001',
    ]);
}

// ─── GPS Check-in ─────────────────────────────────────────────────────────────

test('field officer can record a GPS check-in', function () {
    $officer = fieldOfficer();

    $resp = $this->actingAs($officer)
        ->postJson(route('api.v1.field.check-in'), [
            'latitude'  => -15.4167,
            'longitude' => 28.2833,
            'accuracy'  => 10.5,
            'address'   => '123 Cairo Road, Lusaka',
            'notes'     => 'Visiting borrower',
        ])
        ->assertCreated();

    expect($resp->json('data.latitude'))->toBe(-15.4167)
        ->and($resp->json('data.longitude'))->toBe(28.2833)
        ->and($resp->json('data.checked_in_at'))->not->toBeNull();

    expect(FieldCheckIn::where('user_id', $officer->id)->count())->toBe(1);
});

test('check-in validates required latitude and longitude', function () {
    $officer = fieldOfficer();

    $this->actingAs($officer)
        ->postJson(route('api.v1.field.check-in'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['latitude', 'longitude']);
});

test('officer can list their check-ins', function () {
    $officer = fieldOfficer();

    FieldCheckIn::create([
        'user_id'       => $officer->id,
        'latitude'      => -15.4,
        'longitude'     => 28.3,
        'checked_in_at' => now(),
    ]);
    FieldCheckIn::create([
        'user_id'       => $officer->id,
        'latitude'      => -15.5,
        'longitude'     => 28.4,
        'checked_in_at' => now()->subHour(),
    ]);

    $resp = $this->actingAs($officer)
        ->getJson(route('api.v1.field.check-ins'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(2);
});

// ─── Cash Collection ─────────────────────────────────────────────────────────

test('field officer can record a cash collection', function () {
    $officer = fieldOfficer();
    $loan    = fieldLoan();

    $resp = $this->actingAs($officer)
        ->postJson(route('api.v1.field.collect'), [
            'loan_id'           => $loan->id,
            'amount'            => 500.00,
            'collection_method' => 'cash',
            'latitude'          => -15.4167,
            'longitude'         => 28.2833,
            'notes'             => 'Collected at borrower home',
        ])
        ->assertCreated();

    expect((float) $resp->json('data.amount'))->toBe(500.0)
        ->and($resp->json('data.collection_method'))->toBe('cash')
        ->and($resp->json('data.receipt_number'))->not->toBeNull()
        ->and($resp->json('data.payment_id'))->not->toBeNull();

    expect(FieldCollection::where('loan_id', $loan->id)->count())->toBe(1);
});

test('collection creates a linked payment record', function () {
    $officer = fieldOfficer();
    $loan    = fieldLoan();

    $resp = $this->actingAs($officer)
        ->postJson(route('api.v1.field.collect'), [
            'loan_id'           => $loan->id,
            'amount'            => 300.00,
            'collection_method' => 'mobile_money',
            'reference_number'  => 'MM-REF-12345',
        ])
        ->assertCreated();

    $paymentId = $resp->json('data.payment_id');
    expect($paymentId)->not->toBeNull();

    $collection = FieldCollection::where('loan_id', $loan->id)->first();
    expect($collection->payment_id)->toBe($paymentId);
});

test('collection validates invalid loan_id', function () {
    $officer = fieldOfficer();

    $this->actingAs($officer)
        ->postJson(route('api.v1.field.collect'), [
            'loan_id'           => 99999,
            'amount'            => 100,
            'collection_method' => 'cash',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['loan_id']);
});

test('collection rejects invalid collection method', function () {
    $officer = fieldOfficer();
    $loan    = fieldLoan();

    $this->actingAs($officer)
        ->postJson(route('api.v1.field.collect'), [
            'loan_id'           => $loan->id,
            'amount'            => 100,
            'collection_method' => 'crypto',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['collection_method']);
});

test('officer can list their collections', function () {
    $officer = fieldOfficer();
    $loan    = fieldLoan();

    $this->actingAs($officer)
        ->postJson(route('api.v1.field.collect'), [
            'loan_id'           => $loan->id,
            'amount'            => 200.00,
            'collection_method' => 'cash',
        ]);

    $resp = $this->actingAs($officer)
        ->getJson(route('api.v1.field.collections'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(1);
});

// ─── Loans for officer ────────────────────────────────────────────────────────

test('can fetch active and overdue loans for field work', function () {
    $officer = fieldOfficer();
    fieldLoan(); // creates active loan

    $resp = $this->actingAs($officer)
        ->getJson(route('api.v1.field.loans'))
        ->assertOk();

    $loans = $resp->json('data');
    expect(count($loans))->toBeGreaterThanOrEqual(1);
    expect($loans[0])->toHaveKey('loan_number')
        ->and($loans[0])->toHaveKey('borrower');
});

// ─── Offline Sync ─────────────────────────────────────────────────────────────

test('can submit offline check-in via sync batch', function () {
    $officer = fieldOfficer();

    $resp = $this->actingAs($officer)
        ->postJson(route('api.v1.field.sync'), [
            'items' => [
                [
                    'action'  => 'check_in',
                    'payload' => [
                        'latitude'      => -15.4167,
                        'longitude'     => 28.2833,
                        'checked_in_at' => now()->toDateTimeString(),
                    ],
                ],
            ],
        ])
        ->assertOk();

    expect($resp->json('data.processed'))->toBe(1)
        ->and($resp->json('data.failed'))->toBe(0);

    expect(FieldCheckIn::where('user_id', $officer->id)->count())->toBe(1);
});

test('can submit offline payment collection via sync batch', function () {
    $officer = fieldOfficer();
    $loan    = fieldLoan();

    $resp = $this->actingAs($officer)
        ->postJson(route('api.v1.field.sync'), [
            'items' => [
                [
                    'action'  => 'collect_payment',
                    'payload' => [
                        'loan_id'           => $loan->id,
                        'amount'            => 400.00,
                        'collection_method' => 'cash',
                        'collected_at'      => now()->toDateString(),
                    ],
                ],
            ],
        ])
        ->assertOk();

    expect($resp->json('data.processed'))->toBe(1)
        ->and($resp->json('data.failed'))->toBe(0);

    expect(FieldCollection::where('loan_id', $loan->id)->count())->toBe(1);
});

test('sync batch marks invalid items as failed', function () {
    $officer = fieldOfficer();

    $resp = $this->actingAs($officer)
        ->postJson(route('api.v1.field.sync'), [
            'items' => [
                [
                    'action'  => 'collect_payment',
                    'payload' => [
                        'loan_id'           => 99999, // non-existent
                        'amount'            => 100,
                        'collection_method' => 'cash',
                    ],
                ],
            ],
        ])
        ->assertOk();

    expect($resp->json('data.failed'))->toBe(1)
        ->and($resp->json('data.processed'))->toBe(0);

    expect(OfflineSyncItem::where('status', 'failed')->count())->toBe(1);
});

test('sync batch validates required fields', function () {
    $officer = fieldOfficer();

    $this->actingAs($officer)
        ->postJson(route('api.v1.field.sync'), ['items' => []])
        ->assertUnprocessable();
});

test('can retrieve pending sync items', function () {
    $officer = fieldOfficer();

    OfflineSyncItem::create([
        'user_id' => $officer->id,
        'action'  => 'check_in',
        'payload' => ['latitude' => -15.4, 'longitude' => 28.3],
        'status'  => 'pending',
    ]);

    $resp = $this->actingAs($officer)
        ->getJson(route('api.v1.field.sync.pending'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(1)
        ->and($resp->json('data.0.action'))->toBe('check_in');
});

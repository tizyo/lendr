<?php

use App\Jobs\RecalculateCreditScoreJob;
use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Borrower;
use App\Services\CreditScoringService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

// ─── Cleanup ─────────────────────────────────────────────────────────────────
afterEach(function () {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
    Tenant::all()->each(fn ($t) => $t->delete());
});

// ─── Helpers ─────────────────────────────────────────────────────────────────
function makeVerifTenant(string $plan = 'starter', bool $isVerified = false): Tenant
{
    $tenant = Tenant::create([
        'id'          => (string) Str::uuid(),
        'name'        => 'MFI ' . uniqid(),
        'slug'        => 'mfi-' . uniqid(),
        'plan'        => $plan,
        'status'      => 'active',
        'currency'    => 'ZMW',
        'timezone'    => 'Africa/Lusaka',
        'is_verified' => $isVerified,
    ]);

    return $tenant->refresh();
}

function landlordActor(): LandlordUser
{
    return LandlordUser::factory()->create();
}

// ═══════════════════════════════════════════════════════════════════════════════
// Section 1 — Tenant (Lender) verification badges
// ═══════════════════════════════════════════════════════════════════════════════

it('enterprise tenants automatically get gold badge regardless of is_verified flag', function () {
    $tenant = makeVerifTenant('enterprise', false);

    expect($tenant->verificationBadge())->toBe('gold');
});

it('non-enterprise tenant with is_verified=true gets gold badge', function () {
    $tenant = makeVerifTenant('starter', true);

    expect($tenant->verificationBadge())->toBe('gold');
});

it('non-enterprise tenant with is_verified=false gets no badge', function () {
    $tenant = makeVerifTenant('growth', false);

    expect($tenant->verificationBadge())->toBeNull();
});

it('landlord can grant gold badge to a tenant via POST verify', function () {
    $tenant   = makeVerifTenant('starter');
    $landlord = landlordActor();

    $this->actingAs($landlord, 'sanctum')
        ->postJson("/api/v1/landlord/tenants/{$tenant->id}/verify", [
            'note' => 'Verified company registration.',
        ])
        ->assertOk()
        ->assertJsonPath('data.is_verified', true)
        ->assertJsonPath('data.verification_badge', 'gold')
        ->assertJsonPath('data.verification_note', 'Verified company registration.');

    expect($tenant->fresh()->is_verified)->toBeTrue();
});

it('landlord can revoke gold badge via DELETE verify', function () {
    $tenant = makeVerifTenant('starter', true);
    $tenant->forceFill(['verified_at' => now(), 'verification_note' => 'Previously verified'])->save();

    $landlord = landlordActor();

    $this->actingAs($landlord, 'sanctum')
        ->deleteJson("/api/v1/landlord/tenants/{$tenant->id}/verify")
        ->assertOk()
        ->assertJsonPath('data.is_verified', false)
        ->assertJsonPath('data.verification_badge', null);

    expect($tenant->fresh()->is_verified)->toBeFalse()
        ->and($tenant->fresh()->verified_at)->toBeNull()
        ->and($tenant->fresh()->verification_note)->toBeNull();
});

it('verify endpoint appears in tenant format response for tenant list', function () {
    $tenant   = makeVerifTenant('enterprise');
    $landlord = landlordActor();

    $data = $this->actingAs($landlord, 'sanctum')
        ->getJson("/api/v1/landlord/tenants/{$tenant->id}")
        ->assertOk()
        ->json('data');

    expect($data)->toHaveKey('is_verified')
        ->toHaveKey('verified_at')
        ->toHaveKey('verification_note')
        ->toHaveKey('verification_badge');

    expect($data['verification_badge'])->toBe('gold');
});

it('verify requires authentication', function () {
    $tenant = makeVerifTenant('starter');

    $this->postJson("/api/v1/landlord/tenants/{$tenant->id}/verify")
        ->assertStatus(401);
});

// ═══════════════════════════════════════════════════════════════════════════════
// Section 2 — Borrower tier from credit score
// ═══════════════════════════════════════════════════════════════════════════════

it('tierFromScore returns null for null score', function () {
    expect(Borrower::tierFromScore(null))->toBeNull();
});

it('tierFromScore returns grey for scores 300–549', function () {
    expect(Borrower::tierFromScore(300))->toBe('grey');
    expect(Borrower::tierFromScore(450))->toBe('grey');
    expect(Borrower::tierFromScore(549))->toBe('grey');
});

it('tierFromScore returns yellow for scores 550–699', function () {
    expect(Borrower::tierFromScore(550))->toBe('yellow');
    expect(Borrower::tierFromScore(625))->toBe('yellow');
    expect(Borrower::tierFromScore(699))->toBe('yellow');
});

it('tierFromScore returns blue for scores 700 and above', function () {
    expect(Borrower::tierFromScore(700))->toBe('blue');
    expect(Borrower::tierFromScore(800))->toBe('blue');
    expect(Borrower::tierFromScore(850))->toBe('blue');
});

// ═══════════════════════════════════════════════════════════════════════════════
// Section 3 — RecalculateCreditScoreJob assigns verification_tier
// ═══════════════════════════════════════════════════════════════════════════════

it('RecalculateCreditScoreJob sets verification_tier based on calculated score', function () {
    $tenant = makeVerifTenant('starter');
    tenancy()->initialize($tenant);

    $borrower = Borrower::factory()->create([
        'credit_score'      => null,
        'verification_tier' => null,
    ]);

    // Stub the scoring service to return a known score
    $this->mock(CreditScoringService::class, function ($mock) {
        $mock->shouldReceive('calculate')->once()->andReturn(720);
    });

    (new RecalculateCreditScoreJob($borrower->id))->handle(
        app(CreditScoringService::class)
    );

    $borrower->refresh();

    expect($borrower->credit_score)->toBe(720)
        ->and($borrower->verification_tier)->toBe('blue')
        ->and($borrower->credit_score_updated_at)->not->toBeNull();

    tenancy()->end();
});

it('RecalculateCreditScoreJob assigns yellow tier for score 600', function () {
    $tenant = makeVerifTenant('starter');
    tenancy()->initialize($tenant);

    $borrower = Borrower::factory()->create([
        'credit_score'      => null,
        'verification_tier' => null,
    ]);

    $this->mock(CreditScoringService::class, function ($mock) {
        $mock->shouldReceive('calculate')->once()->andReturn(600);
    });

    (new RecalculateCreditScoreJob($borrower->id))->handle(
        app(CreditScoringService::class)
    );

    expect($borrower->fresh()->verification_tier)->toBe('yellow');

    tenancy()->end();
});

it('RecalculateCreditScoreJob assigns grey tier for score 450', function () {
    $tenant = makeVerifTenant('starter');
    tenancy()->initialize($tenant);

    $borrower = Borrower::factory()->create([
        'credit_score'      => null,
        'verification_tier' => null,
    ]);

    $this->mock(CreditScoringService::class, function ($mock) {
        $mock->shouldReceive('calculate')->once()->andReturn(450);
    });

    (new RecalculateCreditScoreJob($borrower->id))->handle(
        app(CreditScoringService::class)
    );

    expect($borrower->fresh()->verification_tier)->toBe('grey');

    tenancy()->end();
});

it('RecalculateCreditScoreJob silently skips missing borrower', function () {
    $tenant = makeVerifTenant('starter');
    tenancy()->initialize($tenant);

    $this->mock(CreditScoringService::class, function ($mock) {
        $mock->shouldReceive('calculate')->never();
    });

    // Should not throw — missing borrower is a no-op
    (new RecalculateCreditScoreJob(99999))->handle(app(CreditScoringService::class));

    tenancy()->end();

    expect(true)->toBeTrue(); // confirm no exception was thrown
});

<?php

use App\Enums\UserRole;
use App\Models\Tenant\OnboardingStep;
use App\Models\Tenant\User;
use App\Services\TenantOnboardingService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function onboardAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

// ─── Service unit tests ───────────────────────────────────────────────────────

test('progress seeds all 6 steps on first call', function () {
    $svc = app(TenantOnboardingService::class);
    $progress = $svc->progress();

    expect($progress['total_steps'])->toBe(6)
        ->and($progress['completed_steps'])->toBe(0)
        ->and($progress['is_complete'])->toBeFalse();
});

test('progress is idempotent — calling twice does not duplicate', function () {
    $svc = app(TenantOnboardingService::class);
    $svc->progress();
    $svc->progress();

    expect(OnboardingStep::count())->toBe(6);
});

test('complete marks a step done', function () {
    $admin = onboardAdmin();
    $svc = app(TenantOnboardingService::class);
    $svc->progress(); // seed

    $result = $svc->complete('configure_settings', $admin);
    expect($result)->toBeTrue();

    $step = OnboardingStep::where('key', 'configure_settings')->first();
    expect($step->isComplete())->toBeTrue()
        ->and($step->completed_by)->toBe($admin->id);
});

test('complete returns false for already completed step', function () {
    $admin = onboardAdmin();
    $svc = app(TenantOnboardingService::class);
    $svc->progress();
    $svc->complete('configure_settings', $admin);

    $result = $svc->complete('configure_settings', $admin);
    expect($result)->toBeFalse();
});

test('complete returns false for unknown key', function () {
    $admin = onboardAdmin();
    $svc = app(TenantOnboardingService::class);
    $svc->progress();

    $result = $svc->complete('non_existent_step', $admin);
    expect($result)->toBeFalse();
});

test('reset clears a completed step', function () {
    $admin = onboardAdmin();
    $svc = app(TenantOnboardingService::class);
    $svc->progress();
    $svc->complete('configure_settings', $admin);

    $svc->reset('configure_settings');

    $step = OnboardingStep::where('key', 'configure_settings')->first();
    expect($step->isComplete())->toBeFalse();
});

test('isComplete returns true when all required steps done', function () {
    $admin = onboardAdmin();
    $svc = app(TenantOnboardingService::class);
    $progress = $svc->progress();

    $requiredKeys = collect($progress['steps'])->where('is_required', true)->pluck('key');
    foreach ($requiredKeys as $key) {
        $svc->complete($key, $admin);
    }

    expect($svc->isComplete())->toBeTrue();
});

test('completion_pct increases as steps are completed', function () {
    $admin = onboardAdmin();
    $svc = app(TenantOnboardingService::class);

    $before = $svc->progress()['completion_pct'];
    $svc->complete('configure_settings', $admin);
    $after = $svc->progress()['completion_pct'];

    expect($after)->toBeGreaterThan($before);
});

// ─── API endpoint tests ───────────────────────────────────────────────────────

test('GET onboarding/progress returns steps', function () {
    $admin = onboardAdmin();

    $this->actingAs($admin)
        ->getJson(route('api.v1.onboarding.progress'))
        ->assertOk()
        ->assertJsonStructure(['data' => ['steps', 'total_steps', 'completed_steps', 'is_complete', 'completion_pct']]);
});

test('POST onboarding/steps/{key}/complete marks step done', function () {
    $admin = onboardAdmin();

    // Seed steps first
    app(TenantOnboardingService::class)->progress();

    $this->actingAs($admin)
        ->postJson(route('api.v1.onboarding.steps.complete', 'configure_settings'))
        ->assertOk()
        ->assertJsonPath('data.completed_steps', 1);
});

test('POST onboarding/steps/{key}/complete returns 422 for unknown key', function () {
    $admin = onboardAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.onboarding.steps.complete', 'fake_step'))
        ->assertStatus(422);
});

test('unauthenticated cannot access onboarding', function () {
    $this->getJson(route('api.v1.onboarding.progress'))
        ->assertStatus(401);
});

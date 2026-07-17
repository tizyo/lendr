<?php

namespace App\Services;

use App\Models\Tenant\OnboardingStep;
use App\Models\Tenant\User;

class TenantOnboardingService
{
    private const STEPS = [
        [
            'key'         => 'configure_settings',
            'title'       => 'Configure Tenant Settings',
            'description' => 'Set your company name, currency, timezone, and branding.',
            'sort_order'  => 1,
            'is_required' => true,
        ],
        [
            'key'         => 'create_loan_type',
            'title'       => 'Create Your First Loan Product',
            'description' => 'Define a loan type with interest rates and repayment terms.',
            'sort_order'  => 2,
            'is_required' => true,
        ],
        [
            'key'         => 'invite_staff',
            'title'       => 'Invite Staff Members',
            'description' => 'Add loan officers, managers, and other staff to your team.',
            'sort_order'  => 3,
            'is_required' => false,
        ],
        [
            'key'         => 'create_branch',
            'title'       => 'Create a Branch',
            'description' => 'Set up at least one branch or office location.',
            'sort_order'  => 4,
            'is_required' => false,
        ],
        [
            'key'         => 'add_borrower',
            'title'       => 'Add Your First Borrower',
            'description' => 'Register a borrower with their identity and contact details.',
            'sort_order'  => 5,
            'is_required' => true,
        ],
        [
            'key'         => 'disburse_first_loan',
            'title'       => 'Disburse Your First Loan',
            'description' => 'Create and disburse a loan to a borrower.',
            'sort_order'  => 6,
            'is_required' => true,
        ],
    ];

    /**
     * Ensure all steps exist, return full progress.
     */
    public function progress(): array
    {
        $this->seed();

        $steps = OnboardingStep::orderBy('sort_order')->get();

        $required  = $steps->where('is_required', true);
        $completed = $required->whereNotNull('completed_at');

        return [
            'steps'              => $steps->map(fn ($s) => $this->formatStep($s))->values()->toArray(),
            'total_steps'        => $steps->count(),
            'completed_steps'    => $steps->whereNotNull('completed_at')->count(),
            'required_steps'     => $required->count(),
            'required_completed' => $completed->count(),
            'is_complete'        => $required->count() === $completed->count(),
            'completion_pct'     => $steps->count() > 0
                ? round($steps->whereNotNull('completed_at')->count() / $steps->count() * 100)
                : 0,
        ];
    }

    /**
     * Mark a step as completed by a user.
     */
    public function complete(string $key, User $user, array $metadata = []): bool
    {
        $step = OnboardingStep::where('key', $key)->first();

        if (! $step || $step->isComplete()) {
            return false;
        }

        $step->update([
            'completed_at' => now(),
            'completed_by' => $user->id,
            'metadata'     => $metadata ?: null,
        ]);

        return true;
    }

    /**
     * Reset a step (for testing / rollback).
     */
    public function reset(string $key): void
    {
        OnboardingStep::where('key', $key)->update([
            'completed_at' => null,
            'completed_by' => null,
            'metadata'     => null,
        ]);
    }

    public function isComplete(): bool
    {
        return $this->progress()['is_complete'];
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function seed(): void
    {
        foreach (self::STEPS as $step) {
            OnboardingStep::firstOrCreate(
                ['key' => $step['key']],
                $step,
            );
        }
    }

    private function formatStep(OnboardingStep $step): array
    {
        return [
            'key'          => $step->key,
            'title'        => $step->title,
            'description'  => $step->description,
            'is_required'  => $step->is_required,
            'is_complete'  => $step->isComplete(),
            'completed_at' => $step->completed_at?->toIso8601String(),
            'sort_order'   => $step->sort_order,
        ];
    }
}

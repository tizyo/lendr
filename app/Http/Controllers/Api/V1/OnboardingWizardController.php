<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\TenantOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingWizardController extends BaseApiController
{
    public function __construct(private TenantOnboardingService $svc) {}

    /**
     * GET /onboarding/progress
     */
    public function progress(): JsonResponse
    {
        return $this->success($this->svc->progress());
    }

    /**
     * POST /onboarding/steps/{key}/complete
     */
    public function complete(Request $request, string $key): JsonResponse
    {
        $data = $request->validate([
            'metadata' => ['sometimes', 'array'],
        ]);

        $user = $request->user();
        $success = $this->svc->complete($key, $user, $data['metadata'] ?? []);

        if (! $success) {
            return $this->error('Step not found or already completed.', 422);
        }

        return $this->success($this->svc->progress(), 'Step completed.');
    }

    /**
     * POST /onboarding/steps/{key}/reset  (admin/debug use)
     */
    public function reset(string $key): JsonResponse
    {
        $this->svc->reset($key);

        return $this->success(null, 'Step reset.');
    }
}

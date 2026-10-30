<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\ImpersonationSession;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Traits\RunsInTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonationController extends BaseApiController
{
    use RunsInTenantContext;

    /**
     * POST /v1/landlord/tenants/{id}/users/{userId}/impersonate
     * Issues a one-time link that signs the landlord in as this staff member.
     */
    public function start(Request $request, string $id, int $userId): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        $tenant = Tenant::findOrFail($id);

        if (in_array($tenant->status, ['suspended', 'cancelled', 'expired']) || $tenant->isTrialExpired()) {
            return $this->error('This workspace is not active, so its screens are blocked. Reactivate it first.', 422);
        }

        $target = $this->inTenant($tenant, fn () => User::findOrFail($userId));

        if (! $target->is_active) {
            return $this->error('Only active users can be signed in as.', 422);
        }

        [$session, $token] = ImpersonationSession::issue($request->user(), $tenant, $target, $data['reason'], $request);

        $base = $tenant->staffUseCentralHost() ? rtrim((string) config('app.url'), '/') : $tenant->domainUrl();

        return $this->success([
            'url' => $base.'/impersonate/'.$token,
            'session' => $this->format($session),
        ], 'Support session link created.', 201);
    }

    /**
     * GET /v1/landlord/tenants/{id}/impersonations — the tenant's support access history.
     */
    public function index(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $sessions = ImpersonationSession::where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit(20)
            ->get();

        return $this->success($sessions->map(fn ($s) => $this->format($s)));
    }

    /**
     * POST /v1/landlord/impersonations/{sessionId}/end — takes effect on its next request.
     */
    public function end(int $sessionId): JsonResponse
    {
        $session = ImpersonationSession::findOrFail($sessionId);
        $session->end('ended_by_landlord');

        return $this->success($this->format($session), 'Support session ended.');
    }

    private function format(ImpersonationSession $session): array
    {
        return [
            'id' => $session->id,
            'landlord_email' => $session->landlord_email,
            'target_name' => $session->target_name,
            'target_email' => $session->target_email,
            'reason' => $session->reason,
            'status' => $session->status(),
            'created_at' => $session->created_at?->toIso8601String(),
            'started_at' => $session->started_at?->toIso8601String(),
            'expires_at' => $session->expires_at?->toIso8601String(),
            'ended_at' => $session->ended_at?->toIso8601String(),
        ];
    }
}

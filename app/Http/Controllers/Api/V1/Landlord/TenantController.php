<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Jobs\SendLoanEventNotificationJob;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\BorrowerNotification;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TenantController extends BaseApiController
{
    /**
     * GET /v1/landlord/tenants — paginated list.
     */
    public function index(Request $request): JsonResponse
    {
        $tenants = Tenant::query()
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->plan, fn ($q, $p) => $q->where('plan', $p))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('id', 'like', "%{$s}%");
            }))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return $this->success([
            'data' => $tenants->map(fn ($t) => $this->format($t)),
            'pagination' => [
                'total' => $tenants->total(),
                'per_page' => $tenants->perPage(),
                'current_page' => $tenants->currentPage(),
                'last_page' => $tenants->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/landlord/tenants/{id}
     */
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        return $this->success($this->format($tenant, true));
    }

    /**
     * POST /v1/landlord/tenants — provision a new tenant.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'plan' => ['required', Rule::in(['starter', 'growth', 'enterprise'])],
            'currency' => ['nullable', 'string', 'size:3'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'subdomain' => ['nullable', 'string', 'alpha_dash', 'max:63',
                Rule::unique('domains', 'domain'),
            ],
        ]);

        $slug = Str::slug($data['name']);
        $id = (string) Str::uuid();

        $tenant = Tenant::create([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $slug,
            'plan' => $data['plan'],
            'status' => 'trial',
            'currency' => strtoupper($data['currency'] ?? 'ZMW'),
            'timezone' => $data['timezone'] ?? 'Africa/Lusaka',
        ]);

        if (! empty($data['subdomain'])) {
            $tenant->domains()->create(['domain' => $data['subdomain'].'.'.config('app.central_domain', 'lendr.app')]);
        }

        $tenant->createDatabase();
        $tenant->migrateDatabase();

        return $this->success($this->format($tenant), 'Tenant provisioned.', 201);
    }

    /**
     * PUT /v1/landlord/tenants/{id}
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'plan' => ['sometimes', Rule::in(['starter', 'growth', 'enterprise'])],
            'status' => ['sometimes', Rule::in(['trial', 'active', 'suspended', 'cancelled', 'expired'])],
            'currency' => ['sometimes', 'string', 'size:3'],
            'timezone' => ['sometimes', 'string', 'max:64'],
        ]);

        if (isset($data['currency'])) {
            $data['currency'] = strtoupper($data['currency']);
        }

        $tenant->update($data);

        return $this->success($this->format($tenant), 'Tenant updated.');
    }

    /**
     * POST /v1/landlord/tenants/{id}/suspend
     */
    public function suspend(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => 'suspended']);

        return $this->success(null, 'Tenant suspended.');
    }

    /**
     * POST /v1/landlord/tenants/{id}/activate
     */
    public function activate(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['status' => 'active']);

        return $this->success(null, 'Tenant activated.');
    }

    /**
     * POST /v1/landlord/tenants/{id}/change-plan
     * Change a tenant's plan and optionally their status.
     * When upgrading from trial/expired → active, sets status=active automatically.
     */
    public function changePlan(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $data = $request->validate([
            'plan' => ['required', Rule::in(['starter', 'growth', 'enterprise'])],
            'status' => ['sometimes', Rule::in(['trial', 'active', 'suspended', 'cancelled', 'expired'])],
        ]);

        $update = ['plan' => $data['plan']];

        if (isset($data['status'])) {
            $update['status'] = $data['status'];
        } elseif (in_array($tenant->status, ['trial', 'expired'])) {
            // Upgrading from trial/expired → activate automatically
            $update['status'] = 'active';
            $update['trial_ends_at'] = null;
        }

        $tenant->update($update);

        return $this->success($this->format($tenant->fresh()), 'Plan updated.');
    }

    /**
     * POST /v1/landlord/tenants/{id}/change-status
     * Change only the tenant's status with optional trial extension.
     */
    public function changeStatus(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $data = $request->validate([
            'status' => ['required', Rule::in(['trial', 'active', 'suspended', 'cancelled', 'expired'])],
            'trial_ends_at' => ['sometimes', 'nullable', 'date'],
        ]);

        $tenant->update($data);

        return $this->success($this->format($tenant->fresh()), 'Status updated.');
    }

    /**
     * DELETE /v1/landlord/tenants/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->delete();

        return $this->success(null, 'Tenant deleted.');
    }

    /**
     * POST /v1/landlord/tenants/{id}/verify
     * Grant the gold verification badge to a tenant.
     */
    public function verify(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $tenant->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $request->user()?->id,
            'verification_note' => $data['note'] ?? null,
        ]);

        return $this->success($this->format($tenant->fresh()), 'Tenant verified — gold badge granted.');
    }

    /**
     * DELETE /v1/landlord/tenants/{id}/verify
     * Revoke the gold verification badge.
     */
    public function unverify(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $tenant->update([
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
            'verification_note' => null,
        ]);

        return $this->success($this->format($tenant->fresh()), 'Verification revoked.');
    }

    /**
     * POST /v1/landlord/tenants/{id}/push-reminders
     *
     * Sends an overdue payment push notification (in-app + SMS) to every
     * borrower who has at least one unpaid overdue instalment.
     * Rate-limited to once per hour per tenant to prevent abuse.
     */
    public function pushReminders(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $cacheKey = "landlord:push-reminders:{$tenant->id}";
        $cooldownSeconds = 3600; // 1 hour

        if (Cache::has($cacheKey)) {
            $ttl = Cache::get($cacheKey.':expires_at') - now()->timestamp;

            return $this->error(
                'Push reminders already sent recently. Available again in '.ceil($ttl / 60).' minutes.',
                429,
            );
        }

        // Run inside the tenant's database context
        tenancy()->initialize($tenant);

        try {
            // Find loans that have at least one unpaid overdue instalment
            $loanIds = LoanSchedule::query()
                ->where('days_overdue', '>', 0)
                ->where('is_paid', false)
                ->distinct()
                ->pluck('loan_id');

            if ($loanIds->isEmpty()) {
                tenancy()->end();

                return $this->success(['notified' => 0], 'No overdue unpaid loans found.');
            }

            $loans = Loan::with('borrower:id,first_name,last_name,phone,email')
                ->whereIn('id', $loanIds)
                ->where('outstanding_balance', '>', 0)
                ->get();

            // Deduplicate by borrower so a borrower with multiple overdue loans
            // only gets one notification per trigger.
            $seen = [];
            $notified = 0;

            foreach ($loans as $loan) {
                if (! $loan->borrower) {
                    continue;
                }

                $borrowerId = $loan->borrower_id;

                if (isset($seen[$borrowerId])) {
                    continue;
                }

                $seen[$borrowerId] = true;

                // In-app notification
                BorrowerNotification::create([
                    'borrower_id' => $borrowerId,
                    'type' => 'overdue',
                    'title' => 'Payment Overdue',
                    'body' => "Your loan {$loan->loan_number} has an overdue instalment. Please pay to avoid further penalties.",
                    'data' => ['loan_id' => $loan->id, 'loan_number' => $loan->loan_number],
                ]);

                // SMS + email via existing job
                SendLoanEventNotificationJob::dispatch($loan->id, 'overdue');

                $notified++;
            }
        } finally {
            tenancy()->end();
        }

        // Lock for 1 hour
        Cache::put($cacheKey, true, $cooldownSeconds);
        Cache::put($cacheKey.':expires_at', now()->timestamp + $cooldownSeconds, $cooldownSeconds);

        return $this->success(
            ['notified' => $notified, 'cooldown_until' => now()->addSeconds($cooldownSeconds)->toIso8601String()],
            "Payment reminders sent to {$notified} borrower(s).",
        );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function format(Tenant $tenant, bool $full = false): array
    {
        $base = [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'plan' => $tenant->plan,
            'status' => $tenant->status,
            'currency' => $tenant->currency,
            'timezone' => $tenant->timezone,
            'admin_email' => $tenant->admin_email,
            'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
            'created_at' => $tenant->created_at?->toDateTimeString(),
            'is_verified' => $tenant->is_verified,
            'verified_at' => $tenant->verified_at?->toDateTimeString(),
            'verification_note' => $tenant->verification_note,
            'verification_badge' => $tenant->verificationBadge(),
        ];

        if ($full) {
            $base['domains'] = $tenant->domains->pluck('domain');
        }

        return $base;
    }
}

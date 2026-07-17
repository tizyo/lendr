<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\PlanConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET/PUT /v1/landlord/plan-configs
 * Manage the three plan configuration records.
 * Super admin only — auth:sanctum required.
 */
class PlanConfigController extends BaseApiController
{
    /** GET /v1/landlord/plan-configs — return all three plans. */
    public function index(): JsonResponse
    {
        $configs = PlanConfig::orderByRaw("FIELD(plan, 'starter', 'growth', 'enterprise')")
            ->get()
            ->map(fn ($c) => $this->format($c));

        return $this->success($configs);
    }

    /** GET /v1/landlord/plan-configs/{plan} */
    public function show(string $plan): JsonResponse
    {
        $config = PlanConfig::where('plan', $plan)->firstOrFail();

        return $this->success($this->format($config));
    }

    /**
     * PUT /v1/landlord/plan-configs/{plan}
     * Update price and features for the given plan.
     */
    public function update(Request $request, string $plan): JsonResponse
    {
        $config = PlanConfig::where('plan', $plan)->firstOrFail();

        $validated = $request->validate([
            'label'          => ['sometimes', 'string', 'max:100'],
            'description'    => ['nullable', 'string', 'max:500'],
            'price_zmw'      => ['sometimes', 'numeric', 'min:0'],
            'is_custom_price'=> ['sometimes', 'boolean'],
            'features'       => ['sometimes', 'array'],

            // Numeric limits (−1 = unlimited)
            'features.max_users'        => ['sometimes', 'integer', 'min:-1'],
            'features.max_branches'     => ['sometimes', 'integer', 'min:-1'],
            'features.max_loan_products'=> ['sometimes', 'integer', 'min:-1'],
            'features.max_borrowers'    => ['sometimes', 'integer', 'min:-1'],

            // Boolean features
            'features.pwa'                       => ['sometimes', 'boolean'],
            'features.custom_domain'             => ['sometimes', 'boolean'],
            'features.bulk_operations'           => ['sometimes', 'boolean'],
            'features.advanced_reports'          => ['sometimes', 'boolean'],
            'features.collection_management'     => ['sometimes', 'boolean'],
            'features.marketplace'               => ['sometimes', 'boolean'],
            'features.disbursement_mobile_money' => ['sometimes', 'boolean'],
            'features.tenant_website'            => ['sometimes', 'boolean'],
            'features.api_access'                => ['sometimes', 'boolean'],
            'features.exchange_rates'            => ['sometimes', 'boolean'],
            'features.two_factor_auth'           => ['sometimes', 'boolean'],
            'features.audit_log'                 => ['sometimes', 'boolean'],
        ]);

        // Merge features rather than replace entirely
        if (isset($validated['features'])) {
            $validated['features'] = array_merge(
                $config->features ?? [],
                $validated['features']
            );
        }

        $config->update($validated);

        return $this->success($this->format($config->fresh()), 'Plan configuration updated.');
    }

    private function format(PlanConfig $c): array
    {
        return [
            'plan'            => $c->plan,
            'label'           => $c->label,
            'description'     => $c->description,
            'price_zmw'       => (float) $c->price_zmw,
            'is_custom_price' => $c->is_custom_price,
            'features'        => $c->features,
            'updated_at'      => $c->updated_at?->toDateTimeString(),
        ];
    }
}

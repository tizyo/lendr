<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\BillingGatewayConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BillingSettingsController extends BaseApiController
{
    private const GATEWAYS = ['flutterwave', 'pawapay', 'lipila', 'stripe'];

    /**
     * GET /v1/landlord/billing-settings
     * Returns all gateway configs (credentials are masked for security).
     */
    public function index(): JsonResponse
    {
        $configs = BillingGatewayConfig::allIndexed();

        $data = collect(self::GATEWAYS)->map(function (string $gateway) use ($configs) {
            $config = $configs[$gateway] ?? null;
            return $this->format($gateway, $config);
        });

        return $this->success($data);
    }

    /**
     * PUT /v1/landlord/billing-settings/{gateway}
     * Update credentials for a gateway. Pass empty string to clear a field.
     */
    public function update(Request $request, string $gateway): JsonResponse
    {
        if (! in_array($gateway, self::GATEWAYS)) {
            return $this->error('Unknown gateway.', 422);
        }

        $data = $request->validate([
            'public_key'     => ['sometimes', 'nullable', 'string'],
            'secret_key'     => ['sometimes', 'nullable', 'string'],
            'webhook_secret' => ['sometimes', 'nullable', 'string'],
            'extra_config'   => ['sometimes', 'nullable', 'array'],
        ]);

        $config = BillingGatewayConfig::updateOrCreate(
            ['gateway' => $gateway],
            array_filter($data, fn ($v) => $v !== null),
        );

        return $this->success($this->format($gateway, $config), 'Gateway credentials saved.');
    }

    /**
     * POST /v1/landlord/billing-settings/{gateway}/activate
     * Set this gateway as the sole active gateway.
     */
    public function activate(string $gateway): JsonResponse
    {
        if (! in_array($gateway, self::GATEWAYS)) {
            return $this->error('Unknown gateway.', 422);
        }

        // Deactivate all, then activate the chosen one
        BillingGatewayConfig::query()->update(['is_active' => false]);

        $config = BillingGatewayConfig::updateOrCreate(
            ['gateway' => $gateway],
            ['is_active' => true],
        );

        return $this->success($this->format($gateway, $config), ucfirst($gateway) . ' set as active billing gateway.');
    }

    /**
     * POST /v1/landlord/billing-settings/{gateway}/deactivate
     */
    public function deactivate(string $gateway): JsonResponse
    {
        BillingGatewayConfig::where('gateway', $gateway)->update(['is_active' => false]);
        return $this->success(null, 'Gateway deactivated.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function format(string $gateway, ?BillingGatewayConfig $config): array
    {
        return [
            'gateway'         => $gateway,
            'is_active'       => $config?->is_active ?? false,
            'has_public_key'  => ! empty($config?->public_key),
            'has_secret_key'  => ! empty($config?->secret_key),
            'has_webhook_secret' => ! empty($config?->webhook_secret),
            'extra_config'    => $config?->extra_config ?? [],
            'configured'      => ! empty($config?->secret_key),
        ];
    }
}

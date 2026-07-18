<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantWallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Landlord API — configure the payment wallet for an Enterprise tenant.
 *
 * GET    /v1/landlord/tenants/{id}/wallet  — show current wallet config
 * PUT    /v1/landlord/tenants/{id}/wallet  — create or update wallet config
 * DELETE /v1/landlord/tenants/{id}/wallet  — remove wallet config
 */
class TenantWalletController extends BaseApiController
{
    public function show(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $wallet = TenantWallet::where('tenant_id', $tenant->id)->first();

        return $this->success($wallet ? $this->format($wallet) : null);
    }

    public function upsert(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $exists = TenantWallet::where('tenant_id', $tenant->id)->exists();

        $data = $request->validate([
            'gateway' => ['required', 'string', 'in:flutterwave,mtn_momo,airtel_money,pawapay,zamtel_kwacha'],
            'environment' => ['required', 'string', 'in:sandbox,production'],
            'wallet_id' => ['nullable', 'string', 'max:255'],
            'api_key' => $exists ? ['sometimes', 'string', 'max:1000'] : ['required', 'string', 'max:1000'],
            'api_secret' => ['nullable', 'sometimes', 'string', 'max:1000'],
            'webhook_secret' => ['nullable', 'sometimes', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
            'disburse_enabled' => ['boolean'],
            'debit_enabled' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        $wallet = TenantWallet::updateOrCreate(
            ['tenant_id' => $tenant->id],
            $data,
        );

        return $this->success($this->format($wallet), 'Wallet configuration saved.');
    }

    public function destroy(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        TenantWallet::where('tenant_id', $tenant->id)->delete();

        return $this->success(null, 'Wallet configuration removed.');
    }

    private function format(TenantWallet $w): array
    {
        return [
            'id' => $w->id,
            'tenant_id' => $w->tenant_id,
            'gateway' => $w->gateway,
            'environment' => $w->environment,
            'wallet_id' => $w->wallet_id,
            'api_key_set' => ! empty($w->getRawOriginal('api_key')),
            'api_secret_set' => ! empty($w->getRawOriginal('api_secret')),
            'webhook_secret_set' => ! empty($w->getRawOriginal('webhook_secret')),
            'metadata' => $w->metadata ?? [],
            'disburse_enabled' => $w->disburse_enabled,
            'debit_enabled' => $w->debit_enabled,
            'is_active' => $w->is_active,
            'updated_at' => $w->updated_at?->toDateTimeString(),
        ];
    }
}

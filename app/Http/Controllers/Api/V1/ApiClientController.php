<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\ApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApiClientController extends BaseApiController
{
    public function index(): JsonResponse
    {
        return $this->success(ApiClient::orderByDesc('id')->get()->map(fn ($c) => $this->format($c)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['required', 'string', 'max:150'],
            'scopes'                => ['nullable', 'array'],
            'scopes.*'              => ['string', 'in:loan_apply,loan_status,payment_initiate,products_read'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);

        $rawKey = ApiClient::generateKey();

        $client = ApiClient::create([
            'name'                  => $data['name'],
            'client_key'            => $rawKey,
            'client_secret'         => Hash::make($rawKey),
            'scopes'                => $data['scopes'] ?? ['products_read'],
            'rate_limit_per_minute' => $data['rate_limit_per_minute'] ?? 60,
            'created_by'            => auth()->id(),
        ]);

        return $this->success(
            ['client' => $this->format($client) + ['client_key' => $rawKey]],
            'API client created. Store the key — it will not be shown again.',
            201
        );
    }

    public function show(ApiClient $apiClient): JsonResponse
    {
        return $this->success($this->format($apiClient));
    }

    public function update(Request $request, ApiClient $apiClient): JsonResponse
    {
        $data = $request->validate([
            'name'                  => ['sometimes', 'string', 'max:150'],
            'scopes'                => ['sometimes', 'array'],
            'scopes.*'              => ['string', 'in:loan_apply,loan_status,payment_initiate,products_read'],
            'rate_limit_per_minute' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'is_active'             => ['sometimes', 'boolean'],
        ]);

        $apiClient->update($data);

        return $this->success(['client' => $this->format($apiClient->fresh())], 'Client updated.');
    }

    public function destroy(ApiClient $apiClient): JsonResponse
    {
        $apiClient->delete();
        return $this->success(null, 'API client deleted.');
    }

    public function rotateKey(ApiClient $apiClient): JsonResponse
    {
        $rawKey = ApiClient::generateKey();

        $apiClient->update([
            'client_key'    => $rawKey,
            'client_secret' => Hash::make($rawKey),
        ]);

        return $this->success(
            ['client_key' => $rawKey],
            'API key rotated. Store the new key.'
        );
    }

    public function logs(ApiClient $apiClient, Request $request): JsonResponse
    {
        $logs = $apiClient->accessLogs()
            ->limit($request->integer('limit', 50))
            ->get()
            ->map(fn ($l) => [
                'endpoint'         => $l->endpoint,
                'method'           => $l->method,
                'ip_address'       => $l->ip_address,
                'status_code'      => $l->status_code,
                'response_time_ms' => $l->response_time_ms,
                'created_at'       => $l->created_at?->toDateTimeString(),
            ]);

        return $this->success(['logs' => $logs]);
    }

    private function format(ApiClient $c): array
    {
        return [
            'id'                    => $c->id,
            'name'                  => $c->name,
            'client_key_preview'    => $c->client_key ? substr($c->client_key, 0, 12) . '…' : null,
            'scopes'                => $c->scopes,
            'is_active'             => $c->is_active,
            'rate_limit_per_minute' => $c->rate_limit_per_minute,
            'last_used_at'          => $c->last_used_at?->toDateTimeString(),
            'created_at'            => $c->created_at?->toDateTimeString(),
        ];
    }
}

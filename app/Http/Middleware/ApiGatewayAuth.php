<?php

namespace App\Http\Middleware;

use App\Models\Landlord\ApiKeyIndex;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\ApiAccessLog;
use App\Models\Tenant\ApiClient;
use App\Services\PlanFeatureService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Open API authentication: the X-API-Key header.
 *
 * Keys are stored only as SHA-256 hashes. On the shared host (no workspace
 * subdomain) the central key index says which workspace the key belongs to.
 * `api-gateway:loan_apply` also requires that scope on the key.
 */
class ApiGatewayAuth
{
    public function handle(Request $request, Closure $next, ?string $scope = null): Response
    {
        $key = (string) $request->header('X-API-Key');

        if ($key === '') {
            return response()->json(['message' => 'API key required.'], 401);
        }

        $hash = ApiClient::hashKey($key);

        if (! tenancy()->tenant) {
            $tenantId = ApiKeyIndex::where('key_hash', $hash)->value('tenant_id');
            $tenant = $tenantId ? Tenant::find($tenantId) : null;

            if (! $tenant) {
                return response()->json(['message' => 'Invalid or inactive API key.'], 401);
            }

            tenancy()->initialize($tenant);
        }

        if (in_array(tenancy()->tenant->status, ['suspended', 'cancelled', 'expired'], true)) {
            return response()->json(['message' => 'This lender\'s workspace is not active.'], 403);
        }

        $client = ApiClient::where('key_hash', $hash)->first();

        if (! $client || ! $client->is_active) {
            return response()->json(['message' => 'Invalid or inactive API key.'], 401);
        }

        // API access is a plan feature: keys issued on a higher plan stop working after a downgrade.
        if (! PlanFeatureService::forTenant()->has('api_access')) {
            return response()->json(['message' => 'API access is not included in this lender\'s plan.'], 403);
        }

        if ($scope && ! $client->hasScope($scope)) {
            $this->log($client, $request, 403);

            return response()->json(['message' => "This API key doesn't have the \"{$scope}\" scope."], 403);
        }

        // Rate limiting
        $rateLimitKey = 'api-gateway:'.tenancy()->tenant->getTenantKey().':'.$client->id;
        $limit = max(1, (int) $client->rate_limit_per_minute);

        if (RateLimiter::tooManyAttempts($rateLimitKey, $limit)) {
            $this->log($client, $request, 429);

            return response()->json(['message' => 'Too many requests. Rate limit exceeded.'], 429);
        }

        RateLimiter::hit($rateLimitKey, 60);

        // Attach client to request
        $request->attributes->set('api_client', $client);

        $startTime = microtime(true);
        $response = $next($request);

        // Log access
        $this->log($client, $request, $response->getStatusCode(), (int) ((microtime(true) - $startTime) * 1000));

        // Update last_used_at
        $client->update(['last_used_at' => now()]);

        return $response;
    }

    private function log(ApiClient $client, Request $request, int $statusCode, int $ms = 0): void
    {
        ApiAccessLog::create([
            'api_client_id' => $client->id,
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'status_code' => $statusCode,
            'response_time_ms' => $ms,
            'created_at' => now(),
        ]);
    }
}

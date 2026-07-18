<?php

namespace App\Http\Middleware;

use App\Models\Tenant\ApiAccessLog;
use App\Models\Tenant\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiGatewayAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-API-Key');

        if (! $key) {
            return response()->json(['message' => 'API key required.'], 401);
        }

        $client = ApiClient::where('client_key', $key)->first();

        if (! $client || ! $client->is_active) {
            return response()->json(['message' => 'Invalid or inactive API key.'], 401);
        }

        // Rate limiting
        $rateLimitKey = 'api-gateway:'.$client->id;
        $limit = $client->rate_limit_per_minute;

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

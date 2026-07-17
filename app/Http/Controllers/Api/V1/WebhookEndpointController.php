<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\WebhookDelivery;
use App\Models\Tenant\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookEndpointController extends BaseApiController
{
    /**
     * GET /api/v1/webhooks/endpoints
     */
    public function index(): JsonResponse
    {
        $endpoints = WebhookEndpoint::orderByDesc('id')->get()
            ->map(fn ($e) => $this->formatEndpoint($e));

        return $this->success([
            'endpoints'       => $endpoints,
            'available_events' => WebhookEndpoint::availableEvents(),
        ]);
    }

    /**
     * POST /api/v1/webhooks/endpoints
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url'         => ['required', 'url', 'max:500'],
            'events'      => ['required', 'array', 'min:1'],
            'events.*'    => ['string', 'in:'.implode(',', WebhookEndpoint::availableEvents())],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $endpoint = WebhookEndpoint::create([
            ...$data,
            'secret'    => Str::random(32),
            'is_active' => true,
        ]);

        return $this->success(['endpoint' => $this->formatEndpoint($endpoint, true)], 'Webhook endpoint created.', 201);
    }

    /**
     * GET /api/v1/webhooks/endpoints/{endpoint}
     */
    public function show(WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        return $this->success(['endpoint' => $this->formatEndpoint($webhookEndpoint, true)]);
    }

    /**
     * PUT /api/v1/webhooks/endpoints/{endpoint}
     */
    public function update(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $data = $request->validate([
            'url'         => ['sometimes', 'url', 'max:500'],
            'events'      => ['sometimes', 'array', 'min:1'],
            'events.*'    => ['string', 'in:'.implode(',', WebhookEndpoint::availableEvents())],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $webhookEndpoint->update($data);

        return $this->success(['endpoint' => $this->formatEndpoint($webhookEndpoint->fresh())], 'Endpoint updated.');
    }

    /**
     * DELETE /api/v1/webhooks/endpoints/{endpoint}
     */
    public function destroy(WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $webhookEndpoint->delete();

        return $this->success(null, 'Endpoint deleted.');
    }

    /**
     * POST /api/v1/webhooks/endpoints/{endpoint}/rotate-secret
     */
    public function rotateSecret(WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $secret = Str::random(32);
        $webhookEndpoint->update(['secret' => $secret]);

        return $this->success(['secret' => $secret], 'Secret rotated. Store it securely — it will not be shown again.');
    }

    /**
     * GET /api/v1/webhooks/endpoints/{endpoint}/deliveries
     */
    public function deliveries(Request $request, WebhookEndpoint $webhookEndpoint): JsonResponse
    {
        $query = $webhookEndpoint->deliveries()
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('id');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 20)),
            fn ($d) => $this->formatDelivery($d)
        );
    }

    /**
     * POST /api/v1/webhooks/endpoints/{endpoint}/deliveries/{delivery}/retry
     */
    public function retry(WebhookEndpoint $webhookEndpoint, WebhookDelivery $webhookDelivery): JsonResponse
    {
        if ($webhookDelivery->webhook_endpoint_id !== $webhookEndpoint->id) {
            return $this->error('Delivery does not belong to this endpoint.', 404);
        }

        $webhookDelivery->update(['status' => 'pending', 'attempts' => 0, 'next_retry_at' => null]);
        dispatch(new \App\Jobs\DeliverWebhookJob($webhookDelivery->id));

        return $this->success(null, 'Retry queued.');
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function formatEndpoint(WebhookEndpoint $e, bool $withSecret = false): array
    {
        $data = [
            'id'                => $e->id,
            'url'               => $e->url,
            'events'            => $e->events,
            'description'       => $e->description,
            'is_active'         => $e->is_active,
            'failure_count'     => $e->failure_count,
            'last_triggered_at' => $e->last_triggered_at?->toDateTimeString(),
            'last_success_at'   => $e->last_success_at?->toDateTimeString(),
            'created_at'        => $e->created_at->toDateString(),
        ];

        if ($withSecret) {
            $data['secret'] = $e->secret;
        }

        return $data;
    }

    private function formatDelivery(WebhookDelivery $d): array
    {
        return [
            'id'            => $d->id,
            'event'         => $d->event,
            'status'        => $d->status,
            'response_code' => $d->response_code,
            'attempts'      => $d->attempts,
            'delivered_at'  => $d->delivered_at?->toDateTimeString(),
            'created_at'    => $d->created_at->toDateTimeString(),
        ];
    }
}

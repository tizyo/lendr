<?php

namespace App\Services;

use App\Jobs\DeliverWebhookJob;
use App\Models\Tenant\WebhookDelivery;
use App\Models\Tenant\WebhookEndpoint;

class WebhookDispatchService
{
    /**
     * Fire a webhook event to all active, subscribed endpoints.
     */
    public function dispatch(string $event, array $payload): void
    {
        $endpoints = WebhookEndpoint::subscribedTo($event);

        foreach ($endpoints as $endpoint) {
            $delivery = WebhookDelivery::create([
                'webhook_endpoint_id' => $endpoint->id,
                'event'               => $event,
                'payload'             => $payload,
                'status'              => 'pending',
                'attempts'            => 0,
            ]);

            dispatch(new DeliverWebhookJob($delivery->id));
        }
    }
}

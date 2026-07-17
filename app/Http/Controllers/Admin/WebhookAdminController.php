<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\WebhookEndpoint;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WebhookAdminController extends Controller
{
    public function index(): Response
    {
        $endpoints = WebhookEndpoint::withCount('deliveries')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('webhooks/Index', [
            'endpoints'       => $endpoints,
            'availableEvents' => WebhookEndpoint::availableEvents(),
        ]);
    }
}

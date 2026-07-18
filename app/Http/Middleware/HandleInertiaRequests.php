<?php

namespace App\Http\Middleware;

use App\Models\Landlord\PlanConfig;
use App\Models\Tenant\InAppNotification;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function rootView(Request $request): string
    {
        return str_starts_with($request->path(), 'app') ? 'pwa' : 'app';
    }

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Share global data with every Inertia page component.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => fn () => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'avatar' => $request->user()->avatar,
                    'permissions' => $request->user()->getAllPermissions()->pluck('name'),
                    'unread_notifications' => InAppNotification::where('user_id', $request->user()->id)
                        ->whereNull('read_at')
                        ->count(),
                ] : null,
            ],

            'tenant' => fn () => tenancy()->tenant ? [
                'id' => tenancy()->tenant->id,
                'name' => tenancy()->tenant->name,
                'currency' => tenancy()->tenant->currency,
                'logo' => tenancy()->tenant->logo,
                'plan' => tenancy()->tenant->plan,
                'status' => tenancy()->tenant->status,
                'trial_days_remaining' => tenancy()->tenant->trialDaysRemaining(),
                'features' => PlanConfig::forPlan(tenancy()->tenant->plan)?->features ?? [],
            ] : null,

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}

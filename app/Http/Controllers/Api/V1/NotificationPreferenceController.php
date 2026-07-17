<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends BaseApiController
{
    /**
     * GET /api/v1/notification-preferences
     * Returns the authenticated user's preference matrix.
     */
    public function index(): JsonResponse
    {
        $matrix = NotificationPreference::matrixForUser(auth()->id());

        return $this->success([
            'channels' => NotificationPreference::channels(),
            'events'   => NotificationPreference::events(),
            'matrix'   => $matrix,
        ]);
    }

    /**
     * PUT /api/v1/notification-preferences
     * Bulk-update preferences.
     * Body: { preferences: [{ channel, event, is_enabled }] }
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'preferences'                 => ['required', 'array', 'min:1'],
            'preferences.*.channel'       => ['required', 'in:'.implode(',', NotificationPreference::channels())],
            'preferences.*.event'         => ['required', 'in:'.implode(',', NotificationPreference::events())],
            'preferences.*.is_enabled'    => ['required', 'boolean'],
        ]);

        foreach ($data['preferences'] as $pref) {
            NotificationPreference::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'channel' => $pref['channel'],
                    'event'   => $pref['event'],
                ],
                ['is_enabled' => $pref['is_enabled']]
            );
        }

        return $this->success(
            ['matrix' => NotificationPreference::matrixForUser(auth()->id())],
            'Preferences updated.'
        );
    }
}

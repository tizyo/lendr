<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\InAppNotification;
use Illuminate\Http\JsonResponse;

class NotificationController extends BaseApiController
{
    /**
     * GET /api/v1/notifications
     * List notifications for the authenticated user, unread first.
     */
    public function index(): JsonResponse
    {
        $notifications = InAppNotification::where('user_id', auth()->id())
            ->orderByRaw('read_at IS NOT NULL')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($n) => $this->formatNotification($n));

        $unreadCount = InAppNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->count();

        return $this->success([
            'notifications' => $notifications,
            'unread_count'  => $unreadCount,
        ]);
    }

    /**
     * PUT /api/v1/notifications/{id}/read
     */
    public function markRead(int $id): JsonResponse
    {
        $notification = InAppNotification::where('user_id', auth()->id())
            ->findOrFail($id);

        if (! $notification->isRead()) {
            $notification->markRead();
        }

        return $this->success($this->formatNotification($notification->fresh()), 'Notification marked as read.');
    }

    /**
     * PUT /api/v1/notifications/read-all
     */
    public function markAllRead(): JsonResponse
    {
        $count = InAppNotification::where('user_id', auth()->id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success(['updated' => $count], "Marked {$count} notification(s) as read.");
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatNotification(InAppNotification $n): array
    {
        return [
            'id'         => $n->id,
            'type'       => $n->type,
            'title'      => $n->title,
            'body'       => $n->body,
            'icon'       => $n->icon,
            'data'       => $n->data,
            'is_read'    => $n->isRead(),
            'read_at'    => $n->read_at?->toDateTimeString(),
            'created_at' => $n->created_at->toDateTimeString(),
        ];
    }
}

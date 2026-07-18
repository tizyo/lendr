<?php

namespace App\Http\Controllers\Api\V1\Borrower;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\BorrowerNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends BaseApiController
{
    /**
     * GET /api/v1/me/notifications
     */
    public function index(): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = Auth::guard('borrower')->user() ?? Auth::user();

        $notifications = BorrowerNotification::where('borrower_id', $borrower->id)
            ->orderByRaw('read_at IS NOT NULL')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($n) => $this->format($n));

        $unreadCount = BorrowerNotification::where('borrower_id', $borrower->id)
            ->whereNull('read_at')
            ->count();

        return $this->success([
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * PUT /api/v1/me/notifications/{id}/read
     */
    public function markRead(int $id): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = Auth::guard('borrower')->user() ?? Auth::user();

        $notification = BorrowerNotification::where('borrower_id', $borrower->id)->findOrFail($id);

        if (! $notification->isRead()) {
            $notification->markRead();
        }

        return $this->success($this->format($notification->fresh()));
    }

    /**
     * PUT /api/v1/me/notifications/read-all
     */
    public function markAllRead(): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = Auth::guard('borrower')->user() ?? Auth::user();

        $count = BorrowerNotification::where('borrower_id', $borrower->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success(['updated' => $count], "Marked {$count} notification(s) as read.");
    }

    private function format(BorrowerNotification $n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->type,
            'title' => $n->title,
            'body' => $n->body,
            'data' => $n->data,
            'is_read' => $n->isRead(),
            'read_at' => $n->read_at?->toDateTimeString(),
            'created_at' => $n->created_at->diffForHumans(),
        ];
    }
}

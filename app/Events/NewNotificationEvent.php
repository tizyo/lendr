<?php

namespace App\Events;

use App\Models\Tenant\InAppNotification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly InAppNotification $notification,
    ) {}

    /**
     * Private channel scoped to the staff user.
     * Frontend: Echo.private(`staff.{userId}`)
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('staff.'.$this->notification->user_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'notification.new';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'body' => $this->notification->body,
            'icon' => $this->notification->icon,
            'data' => $this->notification->data,
            'created_at' => $this->notification->created_at->toDateTimeString(),
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Tenant\InAppNotification;
use App\Models\Tenant\User;

/**
 * Creates in-app notifications for staff users.
 *
 * Notifications are persisted to the `in_app_notifications` table.
 * The InAppNotification::created() observer broadcasts via Reverb so the
 * admin Topbar bell updates in real time without polling.
 */
class NotificationService
{
    /**
     * Notify all active users that hold any of the given roles.
     *
     * @param  string|string[] $roles  One or more UserRole values (e.g. 'loan_officer', 'branch_manager')
     */
    public function notifyRoles(
        string|array $roles,
        string       $type,
        string       $title,
        string       $body  = '',
        array        $data  = [],
        ?string      $icon  = null,
    ): void {
        $roles   = is_array($roles) ? $roles : [$roles];
        $userIds = User::whereIn('role', $roles)
            ->where('is_active', true)
            ->pluck('id');

        foreach ($userIds as $userId) {
            $this->notifyUser($userId, $type, $title, $body, $data, $icon);
        }
    }

    /**
     * Notify a single staff user by their primary key.
     */
    public function notifyUser(
        int     $userId,
        string  $type,
        string  $title,
        string  $body  = '',
        array   $data  = [],
        ?string $icon  = null,
    ): void {
        InAppNotification::create([
            'user_id' => $userId,
            'type'    => $type,
            'title'   => $title,
            'body'    => $body ?: null,
            'data'    => $data ?: null,
            'icon'    => $icon,
        ]);
    }
}

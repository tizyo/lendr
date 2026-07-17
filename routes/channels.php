<?php

use App\Models\Tenant\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Private channel for each staff user — used by Reverb to push real-time
| notifications to the authenticated user's browser tab.
|
| Channel: staff.{id}
| Auth: only the user whose id matches may subscribe.
|--------------------------------------------------------------------------
*/

Broadcast::channel('staff.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

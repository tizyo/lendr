<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Suspended staff stop being signed in on their next request. Logging in only
 * ever checked is_active once, so an open session outlived a suspension.
 * retrieveByCredentials is untouched so login can still say why it was refused.
 */
class ActiveUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->onlyActive(parent::retrieveById($identifier));
    }

    public function retrieveByToken($identifier, #[\SensitiveParameter] $token): ?Authenticatable
    {
        return $this->onlyActive(parent::retrieveByToken($identifier, $token));
    }

    private function onlyActive(?Authenticatable $user): ?Authenticatable
    {
        return $user?->is_active ? $user : null;
    }
}

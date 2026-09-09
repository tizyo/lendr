<?php

namespace App\Models\Landlord;

use Database\Factories\LandlordUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class LandlordUser extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Central-only data - must not follow the tenant connection swap once
    // tenancy()->initialize() runs, or queries silently hit the wrong DB.
    public function getConnectionName(): ?string
    {
        return config('database.central_connection');
    }

    protected static function newFactory(): LandlordUserFactory
    {
        return LandlordUserFactory::new();
    }

    protected $table = 'landlord_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function has2faEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }
}

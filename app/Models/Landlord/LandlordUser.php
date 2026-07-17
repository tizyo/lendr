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
            'password'                => 'hashed',
            'is_active'               => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at'           => 'datetime',
        ];
    }

    public function has2faEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }
}

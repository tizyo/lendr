<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    protected $fillable = [
        'name',
        'client_key',
        'client_secret',
        'scopes',
        'is_active',
        'rate_limit_per_minute',
        'created_by',
        'last_used_at',
    ];

    protected $hidden = ['client_secret'];

    protected function casts(): array
    {
        return [
            'scopes'          => 'array',
            'is_active'       => 'boolean',
            'last_used_at'    => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(ApiAccessLog::class)->orderByDesc('created_at');
    }

    public static function generateKey(): string
    {
        return 'lndr_' . Str::random(40);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }
}

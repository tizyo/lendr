<?php

namespace App\Models\Landlord;

use App\Models\Tenant\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * A landlord's time-boxed "sign in as" support session, and its audit record.
 * The link is single-use and short-lived; only its hash is stored.
 */
class ImpersonationSession extends Model
{
    /** Key in the Laravel session that marks it as an impersonation. */
    public const SESSION_KEY = 'impersonation_session_id';

    /** Request attribute carrying the live session for the current request. */
    public const REQUEST_ATTRIBUTE = 'impersonation';

    public const LINK_SECONDS = 60;

    public const SESSION_MINUTES = 30;

    // Central-only data - must not follow the tenant connection swap once
    // tenancy()->initialize() runs, or queries silently hit the wrong DB.
    public function getConnectionName(): ?string
    {
        return config('database.central_connection');
    }

    protected $fillable = [
        'landlord_user_id',
        'landlord_email',
        'tenant_id',
        'target_user_id',
        'target_name',
        'target_email',
        'reason',
        'token_hash',
        'link_expires_at',
        'started_at',
        'expires_at',
        'ended_at',
        'ended_reason',
        'ip_address',
        'user_agent',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'link_expires_at' => 'datetime',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return array{0: self, 1: string} the record and the plain link token */
    public static function issue(LandlordUser $landlord, Tenant $tenant, User $target, string $reason, Request $request): array
    {
        $token = Str::random(64);

        $session = static::create([
            'landlord_user_id' => $landlord->id,
            'landlord_email' => $landlord->email,
            'tenant_id' => $tenant->id,
            'target_user_id' => $target->id,
            'target_name' => $target->name,
            'target_email' => $target->email,
            'reason' => $reason,
            'token_hash' => hash('sha256', $token),
            'link_expires_at' => now()->addSeconds(self::LINK_SECONDS),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 490, ''),
        ]);

        return [$session, $token];
    }

    /** The unused, unexpired session a link token belongs to, if any. */
    public static function pending(string $token): ?self
    {
        return static::where('token_hash', hash('sha256', $token))
            ->whereNull('started_at')
            ->whereNull('ended_at')
            ->where('link_expires_at', '>', now())
            ->first();
    }

    /** Spend the link and start the clock. False if another request got there first. */
    public function claim(): bool
    {
        $started = now();

        $claimed = static::whereKey($this->id)
            ->whereNull('started_at')
            ->update([
                'started_at' => $started,
                'expires_at' => $started->copy()->addMinutes(self::SESSION_MINUTES),
                'token_hash' => null,
            ]);

        if ($claimed === 0) {
            return false;
        }

        $this->refresh();

        return true;
    }

    public function isLive(): bool
    {
        return $this->started_at !== null
            && $this->ended_at === null
            && $this->expires_at?->isFuture();
    }

    public function end(string $reason): void
    {
        if ($this->ended_at === null) {
            $this->update(['ended_at' => now(), 'ended_reason' => $reason, 'token_hash' => null]);
        }
    }

    public function status(): string
    {
        return match (true) {
            $this->ended_at !== null => 'ended',
            $this->started_at === null => $this->link_expires_at->isFuture() ? 'pending' : 'unused',
            $this->expires_at?->isPast() => 'expired',
            default => 'active',
        };
    }
}

<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

/**
 * One email sent to a workspace's owner or staff (see RecordOutgoingEmail).
 */
class EmailLog extends Model
{
    use Prunable;

    /** Statuses after which Resend has nothing more to tell us. */
    public const SETTLED = ['delivered', 'opened', 'clicked', 'bounced', 'complained', 'failed', 'canceled'];

    /** Header that ties a message being sent to its row (Resend's API never receives it). */
    public const HEADER = 'X-Lendr-Email-Log';

    protected $fillable = [
        'tenant_id', 'to_email', 'from_email', 'from_name', 'subject', 'mailable', 'context',
        'status', 'provider', 'provider_id', 'error', 'html', 'text', 'has_attachments',
        'resendable', 'sent_by', 'resent_from_id', 'sent_at', 'status_checked_at',
    ];

    protected $hidden = ['html', 'text'];

    protected function casts(): array
    {
        return [
            'has_attachments' => 'boolean',
            'resendable' => 'boolean',
            'sent_at' => 'datetime',
            'status_checked_at' => 'datetime',
        ];
    }

    // Central-only data - must not follow the tenant connection swap once
    // tenancy is initialised (emails are logged from inside tenant requests too).
    public function getConnectionName(): ?string
    {
        return config('database.central_connection');
    }

    /** Bodies hold workspace data; keep six months of history. */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subMonths(6));
    }

    /** A message stuck in "sending" never reached the provider. */
    public function displayStatus(): string
    {
        if ($this->status === 'sending' && $this->created_at?->lt(now()->subMinutes(2))) {
            return 'failed';
        }

        return $this->status;
    }

    public function isTracked(): bool
    {
        return $this->provider === 'resend' && filled($this->provider_id);
    }

    public function needsStatusCheck(): bool
    {
        return $this->isTracked()
            && ! in_array($this->status, self::SETTLED, true)
            && $this->created_at?->gt(now()->subDays(7))
            && (! $this->status_checked_at || $this->status_checked_at->lt(now()->subMinute()));
    }
}

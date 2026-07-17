<?php

namespace App\Models\Tenant;

use App\Enums\KycStatus;
use Database\Factories\Tenant\KycDocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class KycDocument extends Model
{
    use HasFactory, SoftDeletes;

    protected static function newFactory(): KycDocumentFactory
    {
        return KycDocumentFactory::new();
    }

    protected $fillable = [
        'borrower_id',
        'document_type',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'status',
        'reviewed_by',
        'rejection_reason',
        'reviewed_at',
        'expires_at',
        'expiry_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'status'             => KycStatus::class,
            'reviewed_at'        => 'datetime',
            'expires_at'         => 'datetime',
            'expiry_notified_at' => 'datetime',
        ];
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getFileUrlAttribute(): string
    {
        return str_starts_with($this->file_path, 'http')
            ? $this->file_path
            : asset('storage/'.$this->file_path);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /** Attempts to transition to $next; returns false if not allowed by state machine. */
    public function transitionTo(KycStatus $next, array $attributes = []): bool
    {
        if (! $this->status->canTransitionTo($next)) {
            return false;
        }
        $this->update(array_merge(['status' => $next], $attributes));
        return true;
    }
}

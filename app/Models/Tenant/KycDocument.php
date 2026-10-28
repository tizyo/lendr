<?php

namespace App\Models\Tenant;

use App\Enums\KycStatus;
use App\Jobs\SendKycStatusNotificationJob;
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
            'status' => KycStatus::class,
            'reviewed_at' => 'datetime',
            'expires_at' => 'datetime',
            'expiry_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Tell the borrower whenever a decision is made on their document (staff review or expiry).
        static::updated(function (self $document) {
            if ($document->wasChanged('status') && in_array($document->status, SendKycStatusNotificationJob::NOTIFIABLE, true)) {
                SendKycStatusNotificationJob::dispatch($document)->afterCommit();
            }
        });
    }

    public function borrower(): BelongsTo
    {
        return $this->belongsTo(Borrower::class);
    }

    /** Human name of the document type, as the borrower knows it. */
    public function typeLabel(): string
    {
        return match ($this->document_type) {
            'national_id_front' => 'NRC (front)',
            'national_id_back' => 'NRC (back)',
            'passport' => 'passport',
            'drivers_licence' => "driver's licence",
            'utility_bill' => 'utility bill',
            'bank_statement' => 'bank statement',
            'payslip', 'pay_slip' => 'pay slip',
            'selfie' => 'selfie',
            default => str_replace('_', ' ', (string) $this->document_type),
        };
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

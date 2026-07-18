<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanAgreement extends Model
{
    protected $fillable = [
        'loan_id',
        'document_hash',
        'pdf_path',
        'status',
        'otp_hash',
        'otp_expires_at',
        'otp_attempts',
        'signed_by_name',
        'signed_by_phone',
        'signing_ip',
        'signing_device',
        'signed_at',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'otp_expires_at' => 'datetime',
            'signed_at' => 'datetime',
            'otp_attempts' => 'integer',
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function auditEvents(): HasMany
    {
        return $this->hasMany(AgreementAuditEvent::class)->orderBy('occurred_at');
    }

    public function isSigned(): bool
    {
        return $this->status === 'signed';
    }

    public function isOtpValid(string $otp): bool
    {
        return $this->otp_hash
            && $this->otp_expires_at?->isFuture()
            && \Hash::check($otp, $this->otp_hash);
    }
}

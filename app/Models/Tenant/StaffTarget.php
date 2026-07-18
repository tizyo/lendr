<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTarget extends Model
{
    protected $fillable = [
        'user_id',
        'period_month',
        'period_year',
        'disbursement_target',
        'collection_target',
        'new_borrowers_target',
        'new_loans_target',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'disbursement_target' => 'decimal:2',
            'collection_target' => 'decimal:2',
            'new_borrowers_target' => 'integer',
            'new_loans_target' => 'integer',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── Actuals ─────────────────────────────────────────────────────────────

    public function actuals(): array
    {
        $user = $this->user_id;
        $month = $this->period_month;
        $year = $this->period_year;

        $disbursed = Loan::where('created_by', $user)
            ->whereYear('disbursement_date', $year)
            ->whereMonth('disbursement_date', $month)
            ->whereNotNull('disbursement_date')
            ->sum('principal_amount');

        $collected = Payment::where('recorded_by', $user)
            ->whereYear('payment_date', $year)
            ->whereMonth('payment_date', $month)
            ->sum('amount');

        $newBorrowers = Borrower::where('created_by', $user)
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->count();

        $newLoans = Loan::where('created_by', $user)
            ->whereYear('application_date', $year)
            ->whereMonth('application_date', $month)
            ->count();

        return [
            'disbursement_actual' => (float) $disbursed,
            'collection_actual' => (float) $collected,
            'new_borrowers_actual' => (int) $newBorrowers,
            'new_loans_actual' => (int) $newLoans,
        ];
    }

    public function achievementRate(): array
    {
        $actuals = $this->actuals();

        $disbPct = (float) $this->disbursement_target > 0
            ? round(($actuals['disbursement_actual'] / (float) $this->disbursement_target) * 100, 1)
            : null;

        $collPct = (float) $this->collection_target > 0
            ? round(($actuals['collection_actual'] / (float) $this->collection_target) * 100, 1)
            : null;

        $borPct = (int) $this->new_borrowers_target > 0
            ? round(($actuals['new_borrowers_actual'] / (int) $this->new_borrowers_target) * 100, 1)
            : null;

        $loanPct = (int) $this->new_loans_target > 0
            ? round(($actuals['new_loans_actual'] / (int) $this->new_loans_target) * 100, 1)
            : null;

        $valid = array_filter([$disbPct, $collPct, $borPct, $loanPct], fn ($v) => $v !== null);
        $overallPct = count($valid) > 0 ? round(array_sum($valid) / count($valid), 1) : null;

        return [
            'disbursement_pct' => $disbPct,
            'collection_pct' => $collPct,
            'new_borrowers_pct' => $borPct,
            'new_loans_pct' => $loanPct,
            'overall_pct' => $overallPct,
        ];
    }
}

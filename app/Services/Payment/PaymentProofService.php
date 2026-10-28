<?php

namespace App\Services\Payment;

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\BorrowerNotification;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use App\Models\Tenant\PaymentProof;
use App\Models\Tenant\User;
use App\Services\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Bank-deposit repayments (scope 6.4.2): show the tenant's bank details with
 * the loan number as reference → borrower uploads the slip → staff check it
 * against the bank → approval records the Payment (with receipt, GL, fund and
 * borrower notification, exactly like a cashier-entered payment).
 */
class PaymentProofService
{
    public const DISK = 'private';

    /** Loans that can still take repayments (matches PaymentController). */
    public const PAYABLE = [LoanStatus::Active, LoanStatus::Disbursed, LoanStatus::Defaulted, LoanStatus::Frozen];

    public const REVIEWER_ROLES = [UserRole::SuperAdmin->value, UserRole::BranchManager->value, UserRole::Cashier->value, UserRole::Accountant->value];

    public function __construct(
        private readonly PaymentService $payments,
        private readonly NotificationService $notifications,
    ) {}

    /** The tenant's bank account (Settings → General), or null when not set up. */
    public function bankDetails(): ?array
    {
        $s = DB::table('settings')
            ->whereIn('key', ['bank_name', 'bank_account_name', 'bank_account_number', 'bank_branch', 'bank_branch_code'])
            ->pluck('value', 'key');

        if (blank($s->get('bank_name')) || blank($s->get('bank_account_number'))) {
            return null;
        }

        return [
            'bank_name' => $s->get('bank_name'),
            'account_name' => $s->get('bank_account_name'),
            'account_number' => $s->get('bank_account_number'),
            'branch' => $s->get('bank_branch'),
            'branch_code' => $s->get('bank_branch_code'),
        ];
    }

    public function submit(Borrower $borrower, Loan $loan, array $data, UploadedFile $file): PaymentProof
    {
        if (! in_array($loan->status, self::PAYABLE, true)) {
            throw ValidationException::withMessages(['loan' => 'This loan is not accepting repayments.']);
        }

        $path = $file->storeAs(
            "payment-proofs/{$borrower->id}",
            now()->format('YmdHis').'_'.Str::random(6).'.'.strtolower($file->getClientOriginalExtension()),
            self::DISK,
        );

        $proof = PaymentProof::create([
            'loan_id' => $loan->id,
            'borrower_id' => $borrower->id,
            'amount' => $data['amount'],
            'paid_on' => $data['paid_on'],
            'bank_reference' => $data['bank_reference'] ?? null,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'status' => PaymentProof::PENDING,
        ]);

        rescue(fn () => $this->notifications->notifyRoles(
            self::REVIEWER_ROLES,
            'payment_proof_submitted',
            'Bank deposit to verify',
            "{$borrower->full_name} uploaded a deposit slip of ZMW ".number_format((float) $proof->amount, 2)." for {$loan->loan_number}. Check it against the bank before approving.",
            ['payment_proof_id' => $proof->id, 'loan_id' => $loan->id],
            'banknotes',
        ));

        return $proof;
    }

    /**
     * Staff confirmed the deposit on the bank statement. They may correct the
     * amount/date to what the bank actually shows.
     */
    public function approve(PaymentProof $proof, User $reviewer, ?float $amount = null, ?string $paidOn = null): Payment
    {
        return DB::transaction(function () use ($proof, $reviewer, $amount, $paidOn) {
            $proof = PaymentProof::lockForUpdate()->findOrFail($proof->id);

            if (! $proof->isPending()) {
                throw ValidationException::withMessages(['status' => 'This proof has already been reviewed.']);
            }

            if (! in_array($proof->loan->status, self::PAYABLE, true)) {
                throw ValidationException::withMessages(['loan' => 'This loan is no longer accepting repayments.']);
            }

            $payment = $this->payments->record($proof->loan, [
                'amount' => $amount ?? (float) $proof->amount,
                'payment_method' => 'bank_transfer',
                'payment_date' => $paidOn ?? $proof->paid_on->toDateString(),
                'source' => 'manual',
                'reference' => $proof->bank_reference ?: "PROOF-{$proof->id}",
                'notes' => "Bank deposit verified from borrower-uploaded proof #{$proof->id}",
                'recorded_by' => $reviewer->id,
            ]);

            $proof->update([
                'status' => PaymentProof::APPROVED,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'payment_id' => $payment->id,
                'rejection_reason' => null,
            ]);

            return $payment;
        });
    }

    public function reject(PaymentProof $proof, User $reviewer, string $reason): void
    {
        DB::transaction(function () use ($proof, $reviewer, $reason) {
            $proof = PaymentProof::lockForUpdate()->findOrFail($proof->id);

            if (! $proof->isPending()) {
                throw ValidationException::withMessages(['status' => 'This proof has already been reviewed.']);
            }

            $proof->update([
                'status' => PaymentProof::REJECTED,
                'rejection_reason' => $reason,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            BorrowerNotification::create([
                'borrower_id' => $proof->borrower_id,
                'type' => 'payment_proof_rejected',
                'title' => 'Bank deposit not confirmed',
                'body' => 'We couldn\'t match your deposit of ZMW '.number_format((float) $proof->amount, 2)." to {$proof->loan->loan_number}: {$reason}",
                'data' => ['loan_id' => $proof->loan_id, 'payment_proof_id' => $proof->id],
            ]);
        });
    }
}

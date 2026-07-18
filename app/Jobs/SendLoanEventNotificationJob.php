<?php

namespace App\Jobs;

use App\Mail\LoanEventMail;
use App\Models\Tenant\BorrowerNotification;
use App\Models\Tenant\Loan;
use App\Services\Mail\TenantMailService;
use App\Services\SMS\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sends an SMS to the borrower when a loan event occurs.
 *
 * Supported events:
 *   approved          — loan has been approved
 *   disbursed         — funds sent to borrower
 *   payment_received  — repayment recorded
 *   overdue           — installment past due date
 *   defaulted         — loan auto-defaulted
 *   upcoming_payment  — reminder N days before due date
 */
class SendLoanEventNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $loanId,
        public readonly string $event,
        public readonly array $context = [],
    ) {}

    public function handle(SmsService $sms, TenantMailService $mailer): void
    {
        $loan = Loan::with('borrower:id,phone,email,first_name,last_name')->find($this->loanId);

        if (! $loan || ! $loan->borrower) {
            return;
        }

        $message = $this->buildMessage($loan);

        if (! $message) {
            Log::warning("[LoanNotification] No message template for event '{$this->event}'", ['loan_id' => $this->loanId]);

            return;
        }

        // Send SMS
        if ($loan->borrower->phone) {
            $sms->send($loan->borrower->phone, $message);
        }

        // Send email
        if ($loan->borrower->email) {
            $borrowerName = trim(($loan->borrower->first_name ?? '').' '.($loan->borrower->last_name ?? ''));
            $mailer->send($loan->borrower->email, new LoanEventMail(
                borrowerName: $borrowerName ?: 'Valued Customer',
                borrowerEmail: $loan->borrower->email,
                event: $this->event,
                loanNumber: $loan->loan_number,
                context: $this->context,
            ));
        }

        // Create in-app notification for borrower PWA
        $this->createInAppNotification($loan);

        Log::info('[LoanNotification] SMS sent', [
            'loan_id' => $this->loanId,
            'event' => $this->event,
            'phone' => $loan->borrower->phone,
        ]);
    }

    private function createInAppNotification(Loan $loan): void
    {
        [$title, $body] = match ($this->event) {
            'approved' => ['Loan Approved', "Your loan {$loan->loan_number} has been approved!"],
            'disbursed' => ['Funds Disbursed', "Your loan {$loan->loan_number} funds have been sent to your account."],
            'payment_received' => ['Payment Received', 'Your repayment of ZMW '.number_format((float) ($this->context['amount_paid'] ?? 0), 2)." has been confirmed for {$loan->loan_number}."],
            'overdue' => ['Payment Overdue', "Your loan {$loan->loan_number} has an overdue installment. Please pay to avoid penalties."],
            'defaulted' => ['Loan Defaulted', "Your loan {$loan->loan_number} has been marked as defaulted. Please contact us."],
            'upcoming_payment' => ['Payment Reminder', 'Your payment of ZMW '.number_format((float) ($this->context['amount_due'] ?? 0), 2)." for {$loan->loan_number} is due on {$this->context['due_date']}."],
            default => [null, null],
        };

        if ($title && $loan->borrower_id) {
            BorrowerNotification::create([
                'borrower_id' => $loan->borrower_id,
                'type' => $this->event,
                'title' => $title,
                'body' => $body,
                'data' => ['loan_id' => $loan->id, 'loan_number' => $loan->loan_number] + $this->context,
            ]);
        }
    }

    private function buildMessage(Loan $loan): ?string
    {
        $name = $loan->borrower->first_name;
        $number = $loan->loan_number;
        $amount = number_format((float) $loan->outstanding_balance, 2);
        $dueDate = $this->context['due_date'] ?? null;
        $paid = $this->context['amount_paid'] ?? null;

        return match ($this->event) {
            'approved' => "Hi {$name}, your LENDR loan {$number} has been approved! Our team will be in touch shortly. Thank you.",

            'disbursed' => "Hi {$name}, your loan {$number} has been disbursed. Please check your account. Outstanding balance: ZMW {$amount}.",

            'payment_received' => "Hi {$name}, we received your payment of ZMW {$paid} for loan {$number}. Remaining balance: ZMW {$amount}. Thank you!",

            'overdue' => "Hi {$name}, your loan {$number} has an overdue installment. Outstanding: ZMW {$amount}. Please pay to avoid further penalties.",

            'defaulted' => "Hi {$name}, your loan {$number} has been marked as defaulted due to non-payment. Please contact us immediately.",

            'upcoming_payment' => "Reminder: Hi {$name}, your loan {$number} payment of ZMW {$this->context['amount_due']} is due on {$dueDate}. Please ensure funds are available.",

            default => null,
        };
    }
}

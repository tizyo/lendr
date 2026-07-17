<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LoanEventMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $borrowerName,
        public readonly string $borrowerEmail,
        public readonly string $event,
        public readonly string $loanNumber,
        public readonly array  $context = [],
    ) {}

    public function envelope(): Envelope
    {
        $subject = match ($this->event) {
            'approved'         => "Your loan {$this->loanNumber} has been approved",
            'disbursed'        => "Your loan {$this->loanNumber} funds have been disbursed",
            'payment_received' => "Payment confirmed for loan {$this->loanNumber}",
            'overdue'          => "Action required: overdue payment on {$this->loanNumber}",
            'defaulted'        => "Important: loan {$this->loanNumber} marked as defaulted",
            'upcoming_payment' => "Payment reminder for loan {$this->loanNumber}",
            default            => "Update on your loan {$this->loanNumber}",
        };

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.loan-event',
            with: [
                'borrowerName' => $this->borrowerName,
                'event'        => $this->event,
                'loanNumber'   => $this->loanNumber,
                'context'      => $this->context,
                'headline'     => $this->headline(),
                'body'         => $this->bodyText(),
                'ctaLabel'     => 'View Your Loan',
                'ctaUrl'       => url('/app/loans'),
                'isAlert'      => in_array($this->event, ['overdue', 'defaulted']),
            ],
        );
    }

    private function headline(): string
    {
        return match ($this->event) {
            'approved'         => 'Loan Approved!',
            'disbursed'        => 'Funds Disbursed!',
            'payment_received' => 'Payment Received',
            'overdue'          => 'Payment Overdue',
            'defaulted'        => 'Loan Defaulted',
            'upcoming_payment' => 'Payment Reminder',
            default            => 'Loan Update',
        };
    }

    private function bodyText(): string
    {
        $number = $this->loanNumber;
        $name   = $this->borrowerName;

        return match ($this->event) {
            'approved' =>
                "Great news! Your loan application <strong>{$number}</strong> has been approved. Our team will be in touch shortly to finalise the disbursement details.",

            'disbursed' =>
                "Your loan <strong>{$number}</strong> has been disbursed. The funds have been sent to your designated account. You can view your repayment schedule in the borrower portal.",

            'payment_received' =>
                "We have successfully received your payment of <strong>ZMW ".number_format((float) ($this->context['amount_paid'] ?? 0), 2)."</strong> for loan <strong>{$number}</strong>. Your outstanding balance is now <strong>ZMW ".number_format((float) ($this->context['outstanding'] ?? 0), 2)."</strong>. Thank you!",

            'overdue' =>
                "Your loan <strong>{$number}</strong> has an overdue installment. Please make your payment as soon as possible to avoid further penalties. If you are experiencing difficulties, please contact us.",

            'defaulted' =>
                "Your loan <strong>{$number}</strong> has been marked as defaulted due to non-payment. Please contact us immediately to discuss your options.",

            'upcoming_payment' =>
                "This is a reminder that your payment of <strong>ZMW ".number_format((float) ($this->context['amount_due'] ?? 0), 2)."</strong> for loan <strong>{$number}</strong> is due on <strong>".($this->context['due_date'] ?? 'soon')."</strong>. Please ensure funds are available.",

            default => "There has been an update on your loan <strong>{$number}</strong>. Please log in to view the details.",
        };
    }
}

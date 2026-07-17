<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'event',
        'channel',
        'name',
        'subject',
        'body',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Supported events ────────────────────────────────────────────────────

    public static function events(): array
    {
        return [
            'loan_submitted'    => 'Loan Submitted',
            'loan_approved'     => 'Loan Approved',
            'loan_denied'       => 'Loan Denied',
            'loan_disbursed'    => 'Loan Disbursed',
            'payment_received'  => 'Payment Received',
            'payment_reminder'  => 'Payment Reminder',
            'overdue_reminder'  => 'Overdue Reminder',
            'loan_completed'    => 'Loan Completed',
            'welcome'           => 'Welcome / Registration',
            'otp'               => 'OTP Verification',
        ];
    }

    // ─── Available placeholders ───────────────────────────────────────────────

    public static function placeholders(): array
    {
        return [
            '{{borrower_name}}'  => 'Full name of the borrower',
            '{{loan_number}}'    => 'Loan reference number',
            '{{amount}}'         => 'Loan or payment amount',
            '{{due_date}}'       => 'Next due date',
            '{{outstanding}}'    => 'Outstanding balance',
            '{{branch_name}}'    => 'Branch name',
            '{{company_name}}'   => 'Company / tenant name',
            '{{otp}}'            => 'One-time password (OTP)',
        ];
    }

    // ─── Render ───────────────────────────────────────────────────────────────

    /**
     * Replace placeholders in body (and subject for email) with actual values.
     */
    public function render(array $vars): array
    {
        $replace = fn (string $text) => str_replace(
            array_keys($vars),
            array_values($vars),
            $text
        );

        return [
            'subject' => $this->subject ? $replace($this->subject) : null,
            'body'    => $replace($this->body),
        ];
    }

    // ─── Finder ───────────────────────────────────────────────────────────────

    /**
     * Find the active template for a given event + channel, or null if none set.
     */
    public static function findActive(string $event, string $channel): ?self
    {
        return self::where('event', $event)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();
    }
}

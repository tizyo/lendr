<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Mail\LowFundBalanceMail;
use App\Models\Tenant\User;
use App\Services\Mail\TenantMailService;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Scope 8.2 "Fund balance low" → in-app + email to the people who top the
 * fund up and approve disbursements.
 */
class SendLowFundBalanceAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const ROLES = [UserRole::SuperAdmin->value, UserRole::BranchManager->value, UserRole::Accountant->value];

    public int $tries = 3;

    public function __construct(
        public readonly float $balance,
        public readonly float $threshold,
    ) {}

    public function handle(NotificationService $notifications, TenantMailService $mail): void
    {
        $title = 'Disbursement fund is running low';
        $body = 'Available balance is ZMW '.number_format($this->balance, 2)
            .', below your alert level of ZMW '.number_format($this->threshold, 2).'. Record a fund deposit to keep disbursing.';

        $notifications->notifyRoles(self::ROLES, 'fund_low_balance', $title, $body, [
            'balance' => $this->balance,
            'threshold' => $this->threshold,
        ], 'alert-triangle');

        User::whereIn('role', self::ROLES)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->each(fn (User $user) => rescue(fn () => $mail->send($user->email, new LowFundBalanceMail($user, $this->balance, $this->threshold))));
    }
}

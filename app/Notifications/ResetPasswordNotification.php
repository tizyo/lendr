<?php

namespace App\Notifications;

use App\Services\Mail\EmailBrand;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Branded replacement for Laravel's stock reset email. Extends the framework
 * notification so ResetPassword::createUrlUsing() (used by the shared portal
 * to add the workspace to the link) keeps working.
 */
class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $broker = config('auth.defaults.passwords');

        return (new MailMessage)
            ->subject('Reset your '.app(EmailBrand::class)->current()['name'].' password')
            ->view('emails.password-reset', [
                'url' => $this->resetUrl($notifiable),
                'name' => $notifiable->name ?? null,
                'email' => $notifiable->getEmailForPasswordReset(),
                'expireMinutes' => (int) config("auth.passwords.{$broker}.expire", 60),
            ]);
    }
}

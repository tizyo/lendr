<?php

namespace App\Mail\Listeners;

use App\Models\Landlord\EmailLog;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

/**
 * Logs every email sent to a workspace's owner or staff in the central
 * email_logs table, so support can see what went out, whether it was delivered
 * (see EmailDeliveryStatus) and send it again. Borrower emails stay out: they
 * belong to the workspace, not to the platform.
 *
 * A row is written as "sending" before the message leaves and marked "sent"
 * once the provider accepts it; PlatformTransport marks it "failed" when the
 * provider refuses it. Logging never stops an email from being sent.
 *
 * Registered in AppServiceProvider, like ApplyPlatformSender.
 */
class RecordOutgoingEmail
{
    /** Mail whose body must not be kept or replayed (it carries a live secret). */
    private const SECRET_MAILABLES = ['ResetPasswordNotification', 'ResetPassword'];

    public function sending(MessageSending $event): void
    {
        try {
            $email = $event->message;
            if (! $email instanceof Email) {
                return;
            }

            $to = array_map(fn (Address $a) => strtolower($a->getAddress()), $email->getTo());
            if (! $to) {
                return;
            }

            // Support's own sends name their workspace, whoever the address belongs to.
            $meta = is_array($event->data['logMeta'] ?? null) ? $event->data['logMeta'] : [];
            $tenantId = $meta['tenant_id'] ?? $this->tenantFor($to, $event->data);
            if ($tenantId === false) {
                return; // a borrower's email
            }
            $mailable = class_basename($event->data['__laravel_mailable'] ?? $event->data['__laravel_notification'] ?? 'Message');
            $secret = in_array($mailable, self::SECRET_MAILABLES, true);
            $hasAttachments = count($email->getAttachments()) > 0;
            $from = $email->getFrom()[0] ?? null;

            $log = EmailLog::create([
                'tenant_id' => $tenantId,
                'to_email' => Str::limit(implode(', ', $to), 250, ''),
                'from_email' => $from?->getAddress(),
                'from_name' => $from?->getName() ?: null,
                'subject' => Str::limit((string) $email->getSubject(), 250, ''),
                'mailable' => $mailable,
                'context' => tenancy()->initialized ? 'tenant' : 'platform',
                'status' => 'sending',
                'html' => $secret ? null : $this->body($email->getHtmlBody()),
                'text' => $secret ? null : $this->body($email->getTextBody()),
                'has_attachments' => $hasAttachments,
                'resendable' => ! $secret && ! $hasAttachments,
                'sent_by' => $meta['sent_by'] ?? null,
                'resent_from_id' => $meta['resent_from_id'] ?? null,
            ]);

            $email->getHeaders()->addTextHeader(EmailLog::HEADER, (string) $log->id);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function sent(MessageSent $event): void
    {
        try {
            $log = self::logFor($event->message);
            if (! $log) {
                return;
            }

            $providerId = (string) $event->sent->getMessageId();
            // Resend hands back its own UUID; SMTP and the log mailer only a Message-ID header.
            $tracked = Str::isUuid($providerId);

            $log->update([
                'status' => 'sent',
                'provider' => $tracked ? 'resend' : 'other',
                'provider_id' => $tracked ? $providerId : null,
                'sent_at' => now(),
                'error' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Called by PlatformTransport when the provider refuses a message. */
    public static function failed(RawMessage $message, \Throwable $error): void
    {
        try {
            self::logFor($message)?->update(['status' => 'failed', 'error' => Str::limit($error->getMessage(), 1000)]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function logFor(RawMessage $message): ?EmailLog
    {
        if (! $message instanceof Email) {
            return null;
        }

        $id = $message->getHeaders()->get(EmailLog::HEADER)?->getBodyAsString();

        return $id ? EmailLog::find((int) $id) : null;
    }

    /**
     * The workspace an email belongs to: null for platform mail with no workspace,
     * false for mail that isn't ours to log (a workspace emailing its borrowers).
     */
    private function tenantFor(array $to, array $data): string|null|false
    {
        if (tenancy()->initialized) {
            $tenant = tenancy()->tenant;
            $toOwner = $tenant->admin_email && in_array(strtolower($tenant->admin_email), $to, true);

            if ($toOwner || User::whereIn('email', $to)->exists()) {
                return $tenant->id;
            }

            return false;
        }

        if (($data['tenant'] ?? null) instanceof Tenant) {
            return $data['tenant']->id;
        }

        // Platform mail about a workspace (e.g. its owner's verification email).
        $owners = Tenant::whereIn('admin_email', $to)->limit(2)->pluck('id');

        return $owners->count() === 1 ? $owners->first() : null;
    }

    private function body(mixed $body): ?string
    {
        if (is_resource($body)) {
            $body = stream_get_contents($body);
        }

        return is_string($body) && $body !== '' ? $body : null;
    }
}

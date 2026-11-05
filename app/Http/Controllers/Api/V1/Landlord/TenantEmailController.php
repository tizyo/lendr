<?php

namespace App\Http\Controllers\Api\V1\Landlord;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Mail\PlatformMessageMail;
use App\Mail\StoredEmailMail;
use App\Mail\TenantVerificationMail;
use App\Mail\TenantWelcomeMail;
use App\Models\Landlord\EmailLog;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Services\Mail\EmailDeliveryStatus;
use App\Services\Mail\TenantMailService;
use App\Traits\RunsInTenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * The landlord's view of the email a workspace's owner and staff have been sent:
 * what went out, whether it arrived, sending it again, and writing to them.
 */
class TenantEmailController extends BaseApiController
{
    use RunsInTenantContext;

    private const LABELS = [
        'TenantVerificationMail' => 'Email verification',
        'TenantWelcomeMail' => 'Welcome email',
        'StaffInvitationMail' => 'Staff invitation',
        'StaffWelcomeMail' => 'Staff welcome',
        'ResetPasswordNotification' => 'Password reset',
        'TrialExpiryWarningMail' => 'Trial ending reminder',
        'TrialExpiredMail' => 'Trial ended',
        'SubscriptionRenewalMail' => 'Subscription renewal',
        'LowFundBalanceMail' => 'Low fund balance alert',
        'PlatformMessageMail' => 'Message from LENDR',
        'StoredEmailMail' => 'Resent email',
    ];

    private const STATUS_LABELS = [
        'sending' => 'Sending',
        'sent' => 'Sent',
        'delivered' => 'Delivered',
        'opened' => 'Opened',
        'clicked' => 'Clicked',
        'delivery_delayed' => 'Delayed',
        'bounced' => 'Bounced',
        'complained' => 'Marked as spam',
        'failed' => 'Failed',
        'canceled' => 'Cancelled',
        'queued' => 'Queued',
        'scheduled' => 'Scheduled',
    ];

    /** GET /v1/landlord/tenants/{id}/emails */
    public function index(string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        return $this->success($this->list($tenant));
    }

    /** POST /v1/landlord/tenants/{id}/emails/refresh — asks Resend what happened to recent ones. */
    public function refresh(string $id, EmailDeliveryStatus $status): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $status->refreshMany($this->logs($tenant)->limit(30)->get());

        return $this->success($this->list($tenant));
    }

    /** GET /v1/landlord/tenants/{id}/emails/{logId} — the email as it was sent. */
    public function show(string $id, int $logId): JsonResponse
    {
        $log = $this->find($id, $logId);

        return $this->success([
            ...$this->format($log),
            'html' => $log->html,
            'text' => $log->text,
        ]);
    }

    /** POST /v1/landlord/tenants/{id}/emails/{logId}/resend — optionally to a corrected address. */
    public function resend(Request $request, string $id, int $logId): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $log = $this->find($id, $logId);
        $data = $request->validate(['to' => ['nullable', 'email:rfc', 'max:255']]);
        $to = $data['to'] ?? $log->to_email;

        // A verification link is single-use: send a fresh one instead of the old link.
        if ($log->mailable === 'TenantVerificationMail') {
            if ($tenant->isEmailVerified()) {
                return $this->error('This workspace is already verified, so there is nothing to verify. Use "Resend welcome email" instead.', 422);
            }

            return $this->sendOnboarding($request, $tenant, $to);
        }

        if (! $log->resendable || (! $log->html && ! $log->text)) {
            return $this->error($this->whyNotResendable($log), 422);
        }

        if (str_contains($log->to_email, ',') && ! isset($data['to'])) {
            return $this->error('This email went to several people. Enter the one address to send it to.', 422);
        }

        if ($log->mailable === 'StaffInvitationMail' && ! $this->invitationStillOpen($tenant, $log->to_email)) {
            return $this->error('This invitation has already been accepted (or the user was removed), so its link no longer works.', 422);
        }

        $mail = new StoredEmailMail((string) $log->subject, $log->html, $log->text, [
            'tenant_id' => $tenant->id,
            'sent_by' => $request->user()->email,
            'resent_from_id' => $log->id,
        ]);

        return $this->deliver($tenant, $to, $mail, $log->context === 'tenant', 'Email sent again to '.$to.'.');
    }

    /**
     * POST /v1/landlord/tenants/{id}/emails/onboarding — the owner's verification
     * link (with a fresh token) while unverified, their welcome email after.
     */
    public function onboarding(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);
        $data = $request->validate(['to' => ['nullable', 'email:rfc', 'max:255']]);
        $to = $data['to'] ?? $tenant->admin_email;

        if (! $to) {
            return $this->error('This workspace has no owner email. Enter an address to send it to.', 422);
        }

        return $this->sendOnboarding($request, $tenant, $to);
    }

    /** POST /v1/landlord/tenants/{id}/emails — a message written by support. */
    public function send(Request $request, string $id): JsonResponse
    {
        $tenant = Tenant::findOrFail($id);

        $data = $request->validate([
            'recipients' => ['required', 'array', 'min:1', 'max:20'],
            'recipients.*' => ['required', 'email:rfc', 'max:255', 'distinct:ignore_case'],
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:10000'],
            'button_label' => ['nullable', 'required_with:button_url', 'string', 'max:40'],
            'button_url' => ['nullable', 'required_with:button_label', 'url:https,http', 'max:500'],
        ]);

        $names = $this->recipientNames($tenant);
        $sent = [];
        $failed = [];

        foreach ($data['recipients'] as $to) {
            $mail = new PlatformMessageMail(
                $data['subject'],
                $data['body'],
                $names[strtolower($to)] ?? null,
                $data['button_label'] ?? null,
                $data['button_url'] ?? null,
                ['tenant_id' => $tenant->id, 'sent_by' => $request->user()->email],
            );

            try {
                Mail::to($to)->send($mail);
                $sent[] = $to;
            } catch (\Throwable $e) {
                report($e);
                $failed[] = $to.' ('.Str::limit($e->getMessage(), 120).')';
            }
        }

        if ($sent) {
            $this->auditInTenant($tenant, $request, 'LENDR support emailed "'.$data['subject'].'" to '.implode(', ', $sent));
        }

        if (! $sent) {
            return $this->error('The email could not be sent: '.implode('; ', $failed), 502);
        }

        $message = 'Email sent to '.implode(', ', $sent).'.'.($failed ? ' Not sent to: '.implode('; ', $failed).'.' : '');

        return $this->success(['emails' => $this->list($tenant), 'sent' => $sent, 'failed' => $failed], $message);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function sendOnboarding(Request $request, Tenant $tenant, string $to): JsonResponse
    {
        if ($tenant->isEmailVerified()) {
            $mail = new TenantWelcomeMail($tenant, $tenant->loginUrl());
            $done = 'Welcome email sent to '.$to.'.';
        } else {
            $token = Str::random(64);
            $tenant->update(['email_verification_token' => $token]);
            $mail = new TenantVerificationMail($tenant, route('onboarding.verify', $token));
            $done = 'A new verification link was sent to '.$to.'. Earlier links no longer work.';
        }

        $mail->with('logMeta', ['tenant_id' => $tenant->id, 'sent_by' => $request->user()->email]);

        return $this->deliver($tenant, $to, $mail, false, $done);
    }

    /**
     * Sends now (not queued), so support sees straight away whether the provider
     * took it; the page asks Resend about delivery a few seconds later.
     */
    private function deliver(Tenant $tenant, string $to, Mailable $mail, bool $asWorkspace, string $done): JsonResponse
    {
        try {
            if ($asWorkspace) {
                // Sent the way it first was: under the workspace's name, through its own SMTP if it has one.
                $this->inTenant($tenant, fn () => (new TenantMailService)->send($to, $mail));
            } else {
                Mail::to($to)->send($mail);
            }
        } catch (\Throwable $e) {
            report($e);

            return $this->error('The email could not be sent: '.Str::limit($e->getMessage(), 200).' Check Platform Settings → Email.', 502);
        }

        return $this->success(['emails' => $this->list($tenant)], $done);
    }

    private function list(Tenant $tenant): array
    {
        return $this->logs($tenant)->limit(50)->get()->map(fn (EmailLog $log) => $this->format($log))->all();
    }

    /** Metadata only: the bodies are loaded one at a time, when an email is opened. */
    private function logs(Tenant $tenant)
    {
        return EmailLog::where('tenant_id', $tenant->id)
            ->select(['id', 'tenant_id', 'to_email', 'subject', 'mailable', 'context', 'status', 'provider', 'provider_id',
                'error', 'has_attachments', 'resendable', 'sent_by', 'resent_from_id', 'sent_at', 'status_checked_at', 'created_at', 'updated_at'])
            ->selectRaw('(html IS NOT NULL OR text IS NOT NULL) AS has_content')
            ->latest('id');
    }

    private function find(string $tenantId, int $logId): EmailLog
    {
        return EmailLog::where('tenant_id', $tenantId)->findOrFail($logId);
    }

    private function format(EmailLog $log): array
    {
        $status = $log->displayStatus();
        $hasBody = (bool) ($log->getAttributes()['has_content'] ?? ($log->html || $log->text));

        return [
            'id' => $log->id,
            'to' => $log->to_email,
            'subject' => $log->subject,
            'kind' => self::LABELS[$log->mailable] ?? Str::headline((string) $log->mailable),
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status] ?? Str::headline($status),
            // Only Resend tells us what happened after it accepted the message.
            'tracked' => $log->isTracked(),
            'error' => $log->error,
            'sent_by' => $log->sent_by,
            'resent' => (bool) $log->resent_from_id,
            'resendable' => ($log->resendable && $hasBody) || $log->mailable === 'TenantVerificationMail',
            'not_resendable_reason' => ($log->resendable && $hasBody) || $log->mailable === 'TenantVerificationMail' ? null : $this->whyNotResendable($log),
            'has_body' => $hasBody,
            'created_at' => $log->created_at?->toIso8601String(),
            'status_checked_at' => $log->status_checked_at?->toIso8601String(),
        ];
    }

    private function whyNotResendable(EmailLog $log): string
    {
        return match (true) {
            $log->mailable === 'ResetPasswordNotification' => 'Password reset links are never kept. Ask them to request a new one from the sign-in page.',
            $log->has_attachments => 'This email had attachments, so it can\'t be sent again from here.',
            default => 'This email\'s content wasn\'t kept, so it can\'t be sent again.',
        };
    }

    private function invitationStillOpen(Tenant $tenant, string $email): bool
    {
        return (bool) rescue(fn () => $this->inTenant($tenant, fn () => User::where('email', $email)->whereNotNull('invitation_token')->exists()), false);
    }

    /** email → first name, for "Hi Jane," (owner and staff). */
    private function recipientNames(Tenant $tenant): array
    {
        $names = rescue(fn () => $this->inTenant($tenant, fn () => User::pluck('name', 'email')->all()), [], false);

        return collect($names)
            ->mapWithKeys(fn ($name, $email) => [strtolower((string) $email) => Str::of((string) $name)->trim()->before(' ')->toString() ?: null])
            ->all();
    }

    /** The workspace's own audit log shows what support sent them. */
    private function auditInTenant(Tenant $tenant, Request $request, string $description): void
    {
        rescue(fn () => $this->inTenant($tenant, fn () => activity('support')
            ->withProperties(['landlord' => $request->user()->email])
            ->log($description)), report: false);
    }
}

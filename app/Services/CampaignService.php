<?php

namespace App\Services;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\Campaign;
use App\Models\Tenant\CampaignRecipient;
use App\Models\Tenant\Loan;
use App\Services\Mail\TenantMailService;
use App\Services\SMS\SmsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CampaignService
{
    public function __construct(
        private SmsService $sms,
        private TenantMailService $mail,
    ) {}

    /**
     * Resolve the list of borrowers for the campaign's target segment.
     */
    public function resolveRecipients(Campaign $campaign): Collection
    {
        return match ($campaign->target_segment) {
            'all_borrowers'     => Borrower::where('is_active', true)->get(),
            'active_borrowers'  => Borrower::where('is_active', true)
                ->whereHas('loans', fn ($q) => $q->whereIn('status', ['active', 'disbursed']))
                ->get(),
            'overdue_borrowers' => Borrower::where('is_active', true)
                ->whereHas('loans', fn ($q) => $q->whereIn('status', ['active', 'disbursed'])
                    ->whereHas('schedule', fn ($s) => $s->where('is_paid', false)
                        ->where('due_date', '<', now()->toDateString())))
                ->get(),
            'custom'            => Borrower::whereIn('id', $campaign->custom_borrower_ids ?? [])->get(),
            default             => collect(),
        };
    }

    /**
     * Dispatch (send) a campaign to all resolved recipients.
     * Returns a summary of processed/sent/failed counts.
     */
    public function dispatch(Campaign $campaign, bool $dryRun = false): array
    {
        if (! $campaign->isDispatchable()) {
            return ['error' => 'Campaign is not in a dispatchable state.'];
        }

        $borrowers = $this->resolveRecipients($campaign);

        if (! $dryRun) {
            $campaign->update([
                'status'          => 'running',
                'started_at'      => now(),
                'total_recipients' => $borrowers->count(),
            ]);
        }

        $sent   = 0;
        $failed = 0;

        foreach ($borrowers as $borrower) {
            $address = $campaign->type === 'sms'
                ? $borrower->phone
                : $borrower->email;

            if (! $address) {
                $failed++;
                if (! $dryRun) {
                    CampaignRecipient::create([
                        'campaign_id'       => $campaign->id,
                        'borrower_id'       => $borrower->id,
                        'recipient_address' => '',
                        'status'            => 'failed',
                        'error_message'     => 'No ' . $campaign->type . ' address on file.',
                    ]);
                }
                continue;
            }

            $success = $this->sendToRecipient($campaign, $borrower->id, $address, $dryRun);

            if ($success) {
                $sent++;
            } else {
                $failed++;
            }
        }

        if (! $dryRun) {
            $campaign->update([
                'status'     => 'completed',
                'completed_at' => now(),
                'sent_count'  => $sent,
                'failed_count' => $failed,
            ]);
        }

        return [
            'total'    => $borrowers->count(),
            'sent'     => $sent,
            'failed'   => $failed,
            'dry_run'  => $dryRun,
        ];
    }

    /**
     * Send to one recipient and record the result.
     */
    private function sendToRecipient(Campaign $campaign, int $borrowerId, string $address, bool $dryRun): bool
    {
        if ($dryRun) {
            return true;
        }

        $success = false;
        $error   = null;

        try {
            if ($campaign->type === 'sms') {
                $this->sms->send($address, $campaign->content);
                $success = true;
            } else {
                $this->mail->raw(
                    $address,
                    $campaign->subject ?? $campaign->name,
                    $campaign->content
                );
                $success = true;
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        CampaignRecipient::create([
            'campaign_id'       => $campaign->id,
            'borrower_id'       => $borrowerId,
            'recipient_address' => $address,
            'status'            => $success ? 'sent' : 'failed',
            'sent_at'           => $success ? now() : null,
            'error_message'     => $error,
        ]);

        return $success;
    }

    /**
     * Process campaigns that are scheduled and due.
     */
    public function processScheduled(): int
    {
        $due = Campaign::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($due as $campaign) {
            $this->dispatch($campaign);
        }

        return $due->count();
    }

    /**
     * Campaign stats: delivery rate, open rate.
     */
    public function stats(Campaign $campaign): array
    {
        $total   = $campaign->total_recipients;
        $sent    = $campaign->sent_count;
        $failed  = $campaign->failed_count;
        $opened  = $campaign->opened_count;

        return [
            'total_recipients' => $total,
            'sent'             => $sent,
            'failed'           => $failed,
            'opened'           => $opened,
            'delivery_rate'    => $sent > 0 && $total > 0 ? round($sent / $total * 100, 2) : 0.0,
            'open_rate'        => $sent > 0 ? round($opened / $sent * 100, 2) : 0.0,
        ];
    }
}

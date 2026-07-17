<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Campaign;
use App\Models\Tenant\CampaignRecipient;
use App\Services\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends BaseApiController
{
    public function __construct(private CampaignService $service) {}

    public function index(Request $request): JsonResponse
    {
        $campaigns = Campaign::orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($campaigns, fn ($c) => $this->format($c));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'                 => ['required', 'string', 'max:200'],
            'type'                 => ['required', 'in:sms,email'],
            'subject'              => ['nullable', 'string', 'max:200'],
            'content'              => ['required', 'string'],
            'target_segment'       => ['required', 'in:all_borrowers,active_borrowers,overdue_borrowers,custom'],
            'custom_borrower_ids'  => ['nullable', 'array'],
            'custom_borrower_ids.*' => ['integer'],
            'scheduled_at'         => ['nullable', 'date'],
        ]);

        $data['created_by'] = auth()->id();
        $data['status']     = ! empty($data['scheduled_at']) ? 'scheduled' : 'draft';

        $campaign = Campaign::create($data);

        return $this->success(['campaign' => $this->format($campaign)], 'Campaign created.', 201);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        return $this->success($this->format($campaign->load('recipients')));
    }

    public function update(Request $request, Campaign $campaign): JsonResponse
    {
        $data = $request->validate([
            'name'         => ['sometimes', 'string', 'max:200'],
            'subject'      => ['nullable', 'string', 'max:200'],
            'content'      => ['sometimes', 'string'],
            'scheduled_at' => ['nullable', 'date'],
            'status'       => ['sometimes', 'in:draft,scheduled,cancelled'],
        ]);

        $campaign->update($data);

        return $this->success(['campaign' => $this->format($campaign->fresh())], 'Campaign updated.');
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $campaign->delete();
        return $this->success(null, 'Campaign deleted.');
    }

    public function dispatch(Request $request, Campaign $campaign): JsonResponse
    {
        $request->validate(['dry_run' => ['sometimes', 'boolean']]);

        if (! $campaign->isDispatchable()) {
            return $this->error('Campaign cannot be dispatched in its current state.', 422);
        }

        $result = $this->service->dispatch($campaign, (bool) $request->input('dry_run', false));

        return $this->success($result, 'Campaign dispatched.');
    }

    public function stats(Campaign $campaign): JsonResponse
    {
        return $this->success($this->service->stats($campaign), 'Campaign stats.');
    }

    public function trackOpen(Campaign $campaign, CampaignRecipient $recipient): JsonResponse
    {
        if ($recipient->campaign_id !== $campaign->id) {
            return $this->error('Recipient does not belong to this campaign.', 404);
        }

        if ($recipient->status === 'sent') {
            $recipient->update(['status' => 'opened', 'opened_at' => now()]);
            $campaign->increment('opened_count');
        }

        return $this->success(null, 'Tracked.');
    }

    private function format(Campaign $c): array
    {
        return [
            'id'               => $c->id,
            'name'             => $c->name,
            'type'             => $c->type,
            'status'           => $c->status,
            'subject'          => $c->subject,
            'target_segment'   => $c->target_segment,
            'scheduled_at'     => $c->scheduled_at?->toDateTimeString(),
            'started_at'       => $c->started_at?->toDateTimeString(),
            'completed_at'     => $c->completed_at?->toDateTimeString(),
            'total_recipients' => $c->total_recipients,
            'sent_count'       => $c->sent_count,
            'failed_count'     => $c->failed_count,
            'opened_count'     => $c->opened_count,
        ];
    }
}

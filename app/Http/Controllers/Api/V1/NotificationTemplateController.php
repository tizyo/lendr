<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationTemplateController extends BaseApiController
{
    /**
     * GET /api/v1/notification-templates
     * Returns all templates grouped by event, with defaults filled in.
     */
    public function index(): JsonResponse
    {
        $stored = NotificationTemplate::orderBy('event')->orderBy('channel')->get()
            ->keyBy(fn ($t) => "{$t->event}.{$t->channel}");

        $events = NotificationTemplate::events();

        $result = [];
        foreach ($events as $event => $label) {
            $result[] = [
                'event'     => $event,
                'label'     => $label,
                'sms'       => $this->formatOrNull($stored["{$event}.sms"] ?? null),
                'email'     => $this->formatOrNull($stored["{$event}.email"] ?? null),
            ];
        }

        return $this->success([
            'templates'    => $result,
            'placeholders' => NotificationTemplate::placeholders(),
        ]);
    }

    /**
     * PUT /api/v1/notification-templates/{event}/{channel}
     * Upsert a template for a specific event + channel.
     */
    public function upsert(Request $request, string $event, string $channel): JsonResponse
    {
        if (! array_key_exists($event, NotificationTemplate::events())) {
            return $this->error("Unknown event '{$event}'.", 422);
        }

        if (! in_array($channel, ['sms', 'email'])) {
            return $this->error("Channel must be 'sms' or 'email'.", 422);
        }

        $data = $request->validate([
            'name'      => ['nullable', 'string', 'max:150'],
            'subject'   => ['nullable', 'string', 'max:255'],
            'body'      => ['required', 'string', 'max:4000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $template = NotificationTemplate::updateOrCreate(
            ['event' => $event, 'channel' => $channel],
            $data
        );

        return $this->success($this->formatOrNull($template), 'Template saved.');
    }

    /**
     * DELETE /api/v1/notification-templates/{event}/{channel}
     * Remove a template (reverts to system default / no template).
     */
    public function destroy(string $event, string $channel): JsonResponse
    {
        $template = NotificationTemplate::where('event', $event)
            ->where('channel', $channel)
            ->first();

        if (! $template) {
            return $this->error('Template not found.', 404);
        }

        $template->delete();

        return $this->success(null, 'Template deleted.');
    }

    /**
     * POST /api/v1/notification-templates/{event}/{channel}/preview
     * Preview a template with sample data.
     */
    public function preview(Request $request, string $event, string $channel): JsonResponse
    {
        $data = $request->validate([
            'body'    => ['required', 'string'],
            'subject' => ['nullable', 'string'],
        ]);

        $sampleVars = [
            '{{borrower_name}}'  => 'John Doe',
            '{{loan_number}}'    => 'LN-202603-00001',
            '{{amount}}'         => '5,000.00',
            '{{due_date}}'       => now()->addMonth()->format('d M Y'),
            '{{outstanding}}'    => '4,500.00',
            '{{branch_name}}'    => 'Lusaka Branch',
            '{{company_name}}'   => config('app.name'),
            '{{otp}}'            => '123456',
        ];

        $replace = fn (string $text) => str_replace(
            array_keys($sampleVars),
            array_values($sampleVars),
            $text
        );

        return $this->success([
            'subject' => isset($data['subject']) ? $replace($data['subject']) : null,
            'body'    => $replace($data['body']),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatOrNull(?NotificationTemplate $t): ?array
    {
        if (! $t) {
            return null;
        }

        return [
            'id'        => $t->id,
            'event'     => $t->event,
            'channel'   => $t->channel,
            'name'      => $t->name,
            'subject'   => $t->subject,
            'body'      => $t->body,
            'is_active' => $t->is_active,
            'updated_at' => $t->updated_at->format('d M Y H:i'),
        ];
    }
}

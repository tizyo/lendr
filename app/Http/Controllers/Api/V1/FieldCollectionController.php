<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\FieldCheckIn;
use App\Models\Tenant\FieldCollection;
use App\Models\Tenant\Loan;
use App\Models\Tenant\OfflineSyncItem;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FieldCollectionController extends BaseApiController
{
    public function __construct(private readonly PaymentService $payments) {}

    /** POST /api/v1/field/check-in */
    public function checkIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $checkIn = FieldCheckIn::create([
            'user_id' => auth()->id(),
            'latitude' => $data['latitude'],
            'longitude' => $data['longitude'],
            'accuracy' => $data['accuracy'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'checked_in_at' => now(),
        ]);

        return $this->success($this->formatCheckIn($checkIn), 'Check-in recorded.', 201);
    }

    /** GET /api/v1/field/check-ins */
    public function checkIns(Request $request): JsonResponse
    {
        $items = FieldCheckIn::where('user_id', auth()->id())
            ->orderByDesc('checked_in_at')
            ->paginate(20);

        return $this->paginated($items, fn ($c) => $this->formatCheckIn($c));
    }

    /** POST /api/v1/field/collect */
    public function collect(Request $request): JsonResponse
    {
        $data = $request->validate([
            'loan_id' => ['required', 'integer', 'exists:loans,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'collection_method' => ['required', 'in:cash,mobile_money'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string'],
            'collected_at' => ['nullable', 'date'],
        ]);

        $loan = Loan::findOrFail($data['loan_id']);

        // Map field collection method to a valid PaymentMethod enum value
        $paymentMethod = $data['collection_method'] === 'mobile_money' ? 'mtn_momo' : 'cash';

        $payment = $this->payments->record($loan, [
            'amount' => $data['amount'],
            'payment_method' => $paymentMethod,
            'payment_date' => $data['collected_at'] ?? now()->toDateString(),
            'source' => 'manual',
            'reference' => $data['reference_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'recorded_by' => auth()->id(),
        ]);

        $collection = FieldCollection::create([
            'user_id' => auth()->id(),
            'loan_id' => $loan->id,
            'borrower_id' => $loan->borrower_id,
            'amount' => $data['amount'],
            'collection_method' => $data['collection_method'],
            'reference_number' => $data['reference_number'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'receipt_number' => $payment->receipt_number,
            'notes' => $data['notes'] ?? null,
            'payment_id' => $payment->id,
            'collected_at' => $data['collected_at'] ?? now(),
            'synced_at' => now(),
        ]);

        return $this->success($this->formatCollection($collection), 'Collection recorded.', 201);
    }

    /** GET /api/v1/field/collections */
    public function collections(Request $request): JsonResponse
    {
        $items = FieldCollection::where('user_id', auth()->id())
            ->with(['loan', 'borrower'])
            ->orderByDesc('collected_at')
            ->paginate(20);

        return $this->paginated($items, fn ($c) => $this->formatCollection($c));
    }

    /** GET /api/v1/field/loans — loans assigned to the officer, not yet fully paid */
    public function loans(Request $request): JsonResponse
    {
        $loans = Loan::with('borrower')
            ->whereIn('status', ['active', 'overdue'])
            ->orderBy('maturity_date')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'loan_number' => $l->loan_number,
                'status' => $l->status,
                'outstanding' => (float) $l->outstanding_balance,
                'next_due_date' => $l->next_due_date?->toDateString(),
                'borrower' => [
                    'id' => $l->borrower?->id,
                    'name' => $l->borrower ? ($l->borrower->first_name.' '.$l->borrower->last_name) : '—',
                    'phone' => $l->borrower?->phone,
                ],
            ]);

        return $this->success($loans);
    }

    /** POST /api/v1/field/sync — submit batch of offline items */
    public function syncSubmit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.action' => ['required', 'in:check_in,collect_payment'],
            'items.*.payload' => ['required', 'array'],
        ]);

        $results = [];
        foreach ($data['items'] as $item) {
            $sync = OfflineSyncItem::create([
                'user_id' => auth()->id(),
                'action' => $item['action'],
                'payload' => $item['payload'],
                'status' => 'pending',
            ]);

            $result = $this->processSyncItem($sync);
            $results[] = $result;
        }

        $processed = collect($results)->where('status', 'completed')->count();
        $failed = collect($results)->where('status', 'failed')->count();

        return $this->success([
            'processed' => $processed,
            'failed' => $failed,
            'results' => $results,
        ]);
    }

    /** GET /api/v1/field/sync/pending */
    public function syncPending(): JsonResponse
    {
        $items = OfflineSyncItem::where('user_id', auth()->id())
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'action' => $s->action,
                'payload' => $s->payload,
                'created_at' => $s->created_at?->toDateTimeString(),
            ]);

        return $this->success($items);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function processSyncItem(OfflineSyncItem $sync): array
    {
        $sync->update(['status' => 'processing']);

        try {
            if ($sync->action === 'check_in') {
                $payload = $sync->payload;
                FieldCheckIn::create([
                    'user_id' => auth()->id(),
                    'latitude' => $payload['latitude'],
                    'longitude' => $payload['longitude'],
                    'accuracy' => $payload['accuracy'] ?? null,
                    'address' => $payload['address'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                    'checked_in_at' => $payload['checked_in_at'] ?? now(),
                ]);
            } elseif ($sync->action === 'collect_payment') {
                $payload = $sync->payload;
                $loan = Loan::findOrFail($payload['loan_id']);

                $syncMethod = $payload['collection_method'] ?? 'cash';
                $paymentMethod = $syncMethod === 'mobile_money' ? 'mtn_momo' : 'cash';

                $payment = $this->payments->record($loan, [
                    'amount' => $payload['amount'],
                    'payment_method' => $paymentMethod,
                    'payment_date' => $payload['collected_at'] ?? now()->toDateString(),
                    'source' => 'manual',
                    'reference' => $payload['reference_number'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                    'recorded_by' => auth()->id(),
                ]);

                FieldCollection::create([
                    'user_id' => auth()->id(),
                    'loan_id' => $loan->id,
                    'borrower_id' => $loan->borrower_id,
                    'amount' => $payload['amount'],
                    'collection_method' => $syncMethod,
                    'reference_number' => $payload['reference_number'] ?? null,
                    'latitude' => $payload['latitude'] ?? null,
                    'longitude' => $payload['longitude'] ?? null,
                    'receipt_number' => $payment->receipt_number,
                    'notes' => $payload['notes'] ?? null,
                    'payment_id' => $payment->id,
                    'collected_at' => $payload['collected_at'] ?? now(),
                    'synced_at' => now(),
                ]);
            }

            $sync->update(['status' => 'completed', 'processed_at' => now()]);

            return ['id' => $sync->id, 'action' => $sync->action, 'status' => 'completed'];
        } catch (\Throwable $e) {
            $sync->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            return ['id' => $sync->id, 'action' => $sync->action, 'status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    private function formatCheckIn(FieldCheckIn $c): array
    {
        return [
            'id' => $c->id,
            'user_id' => $c->user_id,
            'latitude' => $c->latitude,
            'longitude' => $c->longitude,
            'accuracy' => $c->accuracy,
            'address' => $c->address,
            'notes' => $c->notes,
            'checked_in_at' => $c->checked_in_at?->toDateTimeString(),
        ];
    }

    private function formatCollection(FieldCollection $c): array
    {
        return [
            'id' => $c->id,
            'user_id' => $c->user_id,
            'loan_id' => $c->loan_id,
            'borrower_id' => $c->borrower_id,
            'amount' => $c->amount,
            'collection_method' => $c->collection_method,
            'reference_number' => $c->reference_number,
            'receipt_number' => $c->receipt_number,
            'payment_id' => $c->payment_id,
            'latitude' => $c->latitude,
            'longitude' => $c->longitude,
            'notes' => $c->notes,
            'collected_at' => $c->collected_at?->toDateTimeString(),
            'synced_at' => $c->synced_at?->toDateTimeString(),
        ];
    }
}

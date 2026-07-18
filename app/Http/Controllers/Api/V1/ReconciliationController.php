<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\BankStatement;
use App\Models\Tenant\BankTransaction;
use App\Models\Tenant\Payment;
use App\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReconciliationController extends BaseApiController
{
    public function __construct(private ReconciliationService $service) {}

    /** GET /reconciliation — list all bank statements */
    public function index(Request $request): JsonResponse
    {
        $statements = BankStatement::withCount('transactions')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($statements, fn ($s) => $this->service->report($s));
    }

    /** POST /reconciliation/import — upload and parse a CSV */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'statement_from' => ['nullable', 'date'],
            'statement_to' => ['nullable', 'date'],
        ]);

        $csv = file_get_contents($request->file('file')->getRealPath());
        $filename = $request->file('file')->getClientOriginalName();

        $statement = $this->service->importCsv($csv, $filename, $request->user()->id, [
            'bank_name' => $request->bank_name,
            'statement_from' => $request->statement_from,
            'statement_to' => $request->statement_to,
        ]);

        return $this->success($this->service->report($statement), 'Bank statement imported.', 201);
    }

    /** POST /reconciliation/{statement}/reconcile — run auto-matching */
    public function reconcile(BankStatement $statement): JsonResponse
    {
        $result = $this->service->reconcile($statement);

        return $this->success(array_merge($result, $this->service->report($statement->fresh())), 'Reconciliation complete.');
    }

    /** GET /reconciliation/{statement} — show statement with report */
    public function show(BankStatement $statement): JsonResponse
    {
        return $this->success($this->service->report($statement->load('transactions')));
    }

    /** GET /reconciliation/{statement}/unmatched — list unmatched transactions */
    public function unmatched(BankStatement $statement): JsonResponse
    {
        $txns = $this->service->unmatchedQueue($statement)->map(fn ($t) => $this->formatTx($t));

        return $this->success($txns);
    }

    /** POST /reconciliation/transactions/{transaction}/match — manually match to a payment */
    public function match(Request $request, BankTransaction $transaction): JsonResponse
    {
        $request->validate([
            'payment_id' => ['required', 'exists:payments,id'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = Payment::findOrFail($request->payment_id);
        $this->service->manualMatch($transaction, $payment, $request->notes ?? '');

        return $this->success($this->formatTx($transaction->fresh()), 'Transaction matched.');
    }

    /** POST /reconciliation/transactions/{transaction}/ignore */
    public function ignore(Request $request, BankTransaction $transaction): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->service->ignore($transaction, $request->reason ?? '');

        return $this->success($this->formatTx($transaction->fresh()), 'Transaction ignored.');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatTx(BankTransaction $t): array
    {
        return [
            'id' => $t->id,
            'transaction_date' => $t->transaction_date->toDateString(),
            'reference' => $t->reference,
            'description' => $t->description,
            'amount' => (float) $t->amount,
            'type' => $t->type,
            'match_status' => $t->match_status,
            'matched_payment' => $t->matched_payment_id,
            'match_notes' => $t->match_notes,
        ];
    }
}

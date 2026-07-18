<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\GlAccount;
use App\Models\Tenant\GlJournalEntry;
use App\Services\GlLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlLedgerController extends BaseApiController
{
    public function __construct(private GlLedgerService $ledger) {}

    // ─── Chart of Accounts ────────────────────────────────────────────────────

    /**
     * GET /api/v1/gl/accounts
     */
    public function accounts(): JsonResponse
    {
        $accounts = GlAccount::orderBy('code')->get()->map(fn ($a) => [
            'id' => $a->id,
            'code' => $a->code,
            'name' => $a->name,
            'type' => $a->type,
            'is_active' => $a->is_active,
            'description' => $a->description,
            'balance' => $a->balance(),
        ]);

        return $this->success(['accounts' => $accounts]);
    }

    /**
     * POST /api/v1/gl/accounts
     */
    public function createAccount(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:gl_accounts,code'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:asset,liability,equity,income,expense'],
            'description' => ['nullable', 'string'],
        ]);

        $account = GlAccount::create($data);

        return $this->success([
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => 0.0,
            ],
        ], 'Account created.', 201);
    }

    // ─── Journal Entries ──────────────────────────────────────────────────────

    /**
     * GET /api/v1/gl/entries
     */
    public function entries(Request $request): JsonResponse
    {
        $query = GlJournalEntry::with('lines.account', 'creator')
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($request->filled('account_code')) {
            $query->whereHas('lines.account', fn ($q) => $q->where('code', $request->account_code));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_date', '<=', $request->date_to);
        }

        $entries = $query->paginate($request->integer('per_page', 30));

        return $this->success([
            'entries' => $entries->through(fn ($e) => $this->formatEntry($e)),
        ]);
    }

    /**
     * POST /api/v1/gl/entries
     * Manual journal entry — must be balanced.
     */
    public function createEntry(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'max:500'],
            'entry_date' => ['required', 'date'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_code' => ['required', 'string', 'exists:gl_accounts,code'],
            'lines.*.side' => ['required', 'in:debit,credit'],
            'lines.*.amount' => ['required', 'numeric', 'min:0.01'],
            'lines.*.notes' => ['nullable', 'string'],
        ]);

        $debits = collect($data['lines'])->where('side', 'debit')->sum('amount');
        $credits = collect($data['lines'])->where('side', 'credit')->sum('amount');

        if (abs($debits - $credits) > 0.01) {
            return $this->error('Journal entry is not balanced (debits must equal credits).', 422);
        }

        try {
            $entry = $this->ledger->post(
                $data['description'],
                $data['lines'],
                null,
                $data['entry_date'],
                auth()->id(),
            );
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(['entry' => $this->formatEntry($entry->load('lines.account'))], 'Journal entry posted.', 201);
    }

    // ─── Trial Balance ────────────────────────────────────────────────────────

    /**
     * GET /api/v1/gl/trial-balance
     */
    public function trialBalance(): JsonResponse
    {
        $rows = $this->ledger->trialBalance();

        $totalDebits = collect($rows)->whereIn('type', ['asset', 'expense'])->sum('balance');
        $totalCredits = collect($rows)->whereIn('type', ['liability', 'equity', 'income'])->sum('balance');

        return $this->success([
            'accounts' => $rows,
            'total_debits' => round($totalDebits, 2),
            'total_credits' => round($totalCredits, 2),
            'is_balanced' => abs($totalDebits - $totalCredits) < 0.01,
        ]);
    }

    // ─── Seed Default Accounts ────────────────────────────────────────────────

    /**
     * POST /api/v1/gl/seed-accounts
     * Seed the default chart of accounts (idempotent).
     */
    public function seedAccounts(): JsonResponse
    {
        $seeded = 0;

        foreach (GlAccount::defaultAccounts() as $accountData) {
            GlAccount::firstOrCreate(['code' => $accountData['code']], $accountData);
            $seeded++;
        }

        return $this->success(['seeded' => $seeded], 'Chart of accounts seeded.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function formatEntry(GlJournalEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'reference' => $entry->reference,
            'entry_date' => $entry->entry_date?->format('d M Y'),
            'description' => $entry->description,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'lines' => $entry->lines->map(fn ($l) => [
                'account_code' => $l->account?->code,
                'account_name' => $l->account?->name,
                'side' => $l->side,
                'amount' => (float) $l->amount,
                'notes' => $l->notes,
            ])->values(),
        ];
    }
}

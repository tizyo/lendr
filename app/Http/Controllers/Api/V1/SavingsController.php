<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\SavingsAccount;
use App\Models\Tenant\SavingsTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavingsController extends BaseApiController
{
    // ─── Accounts ─────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/savings
     */
    public function index(Request $request): JsonResponse
    {
        $query = SavingsAccount::with('borrower')
            ->when($request->borrower_id, fn ($q, $v) => $q->where('borrower_id', $v))
            ->when($request->status,      fn ($q, $v) => $q->where('status', $v))
            ->when($request->type,        fn ($q, $v) => $q->where('type', $v))
            ->orderByDesc('id');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 20)),
            fn ($a) => $this->formatAccount($a)
        );
    }

    /**
     * POST /api/v1/savings
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'borrower_id'   => ['required', 'exists:borrowers,id'],
            'type'          => ['required', 'in:regular,fixed,target'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'maturity_date' => ['nullable', 'date', 'after:today'],
            'target_amount' => ['nullable', 'numeric', 'min:0.01'],
            'notes'         => ['nullable', 'string'],
        ]);

        $account = SavingsAccount::create([
            ...$data,
            'account_number' => SavingsAccount::generateAccountNumber(),
            'opened_by'      => auth()->id(),
            'opened_date'    => now()->toDateString(),
            'balance'        => 0,
        ]);

        return $this->success(['account' => $this->formatAccount($account->load('borrower'))], 'Savings account opened.', 201);
    }

    /**
     * GET /api/v1/savings/{account}
     */
    public function show(SavingsAccount $savings): JsonResponse
    {
        return $this->success(['account' => $this->formatAccount($savings->load('borrower', 'transactions'))]);
    }

    /**
     * PUT /api/v1/savings/{account}/status
     */
    public function updateStatus(Request $request, SavingsAccount $savings): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,dormant,closed'],
        ]);

        $savings->update($data);

        return $this->success(['account' => $this->formatAccount($savings->fresh())], 'Account status updated.');
    }

    // ─── Transactions ─────────────────────────────────────────────────────────

    /**
     * POST /api/v1/savings/{account}/deposit
     */
    public function deposit(Request $request, SavingsAccount $savings): JsonResponse
    {
        $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes'     => ['nullable', 'string'],
        ]);

        if ($savings->status !== 'active') {
            return $this->error('Cannot deposit into a '.$savings->status.' account.', 422);
        }

        $txn = DB::transaction(function () use ($request, $savings) {
            $newBalance = (float) $savings->balance + (float) $request->amount;
            $savings->update(['balance' => $newBalance]);

            return $savings->transactions()->create([
                'recorded_by'      => auth()->id(),
                'type'             => 'deposit',
                'amount'           => $request->amount,
                'balance_after'    => $newBalance,
                'reference'        => $request->reference,
                'notes'            => $request->notes,
                'transaction_date' => now()->toDateString(),
            ]);
        });

        return $this->success(['transaction' => $this->formatTxn($txn), 'balance' => (float) $savings->fresh()->balance], 'Deposit recorded.', 201);
    }

    /**
     * POST /api/v1/savings/{account}/withdraw
     */
    public function withdraw(Request $request, SavingsAccount $savings): JsonResponse
    {
        $request->validate([
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes'     => ['nullable', 'string'],
        ]);

        if ($savings->status !== 'active') {
            return $this->error('Cannot withdraw from a '.$savings->status.' account.', 422);
        }

        if ((float) $request->amount > (float) $savings->balance) {
            return $this->error('Insufficient balance. Available: '.(float) $savings->balance, 422);
        }

        $txn = DB::transaction(function () use ($request, $savings) {
            $newBalance = (float) $savings->balance - (float) $request->amount;
            $savings->update(['balance' => $newBalance]);

            return $savings->transactions()->create([
                'recorded_by'      => auth()->id(),
                'type'             => 'withdrawal',
                'amount'           => $request->amount,
                'balance_after'    => $newBalance,
                'reference'        => $request->reference,
                'notes'            => $request->notes,
                'transaction_date' => now()->toDateString(),
            ]);
        });

        return $this->success(['transaction' => $this->formatTxn($txn), 'balance' => (float) $savings->fresh()->balance], 'Withdrawal recorded.', 201);
    }

    /**
     * POST /api/v1/savings/{account}/accrue-interest
     */
    public function accrueInterest(SavingsAccount $savings): JsonResponse
    {
        $txn = $savings->accrueInterest();

        if (! $txn) {
            return $this->error('No interest to accrue (zero balance or zero rate).', 422);
        }

        return $this->success([
            'transaction' => $this->formatTxn($txn),
            'balance'     => (float) $savings->fresh()->balance,
        ], 'Interest accrued.');
    }

    /**
     * GET /api/v1/savings/{account}/statement
     */
    public function statement(Request $request, SavingsAccount $savings): JsonResponse
    {
        $query = $savings->transactions()
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('transaction_date', '>=', $d))
            ->when($request->date_to,   fn ($q, $d) => $q->whereDate('transaction_date', '<=', $d))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 50)),
            fn ($t) => $this->formatTxn($t)
        );
    }

    /**
     * GET /api/v1/savings/{account}/goal-progress
     * For type=target accounts: returns progress toward the target amount.
     */
    public function goalProgress(SavingsAccount $savings): JsonResponse
    {
        if ($savings->type !== 'target') {
            return $this->error('Goal progress is only available for target savings accounts.', 422);
        }

        $target   = (float) $savings->target_amount;
        $balance  = (float) $savings->balance;
        $progress = $target > 0 ? round(($balance / $target) * 100, 2) : 0.0;

        return $this->success([
            'account_id'    => $savings->id,
            'balance'       => $balance,
            'target_amount' => $target,
            'progress_pct'  => $progress,
            'remaining'     => max(0, round($target - $balance, 2)),
            'achieved'      => $balance >= $target,
        ]);
    }

    /**
     * GET /api/v1/savings/matured
     * Fixed deposit accounts whose maturity_date has passed and are still active.
     */
    public function matured(Request $request): JsonResponse
    {
        $accounts = SavingsAccount::with('borrower')
            ->where('type', 'fixed')
            ->where('status', 'active')
            ->whereDate('maturity_date', '<=', now()->toDateString())
            ->orderBy('maturity_date')
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($accounts, fn ($a) => $this->formatAccount($a));
    }

    /**
     * POST /api/v1/savings/{account}/mature
     * Closes a matured fixed deposit, accruing final interest and marking closed.
     */
    public function matureFd(SavingsAccount $savings): JsonResponse
    {
        if ($savings->type !== 'fixed') {
            return $this->error('Only fixed deposit accounts can be matured.', 422);
        }

        if ($savings->status !== 'active') {
            return $this->error('Account is not active.', 422);
        }

        if ($savings->maturity_date && $savings->maturity_date->isFuture()) {
            return $this->error('Maturity date has not been reached yet.', 422);
        }

        // Accrue any final interest before closing
        $savings->accrueInterest();

        $savings->update(['status' => 'closed']);

        return $this->success([
            'account' => $this->formatAccount($savings->fresh('borrower')),
        ], 'Fixed deposit matured and closed.');
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function formatAccount(SavingsAccount $a): array
    {
        return [
            'id'             => $a->id,
            'account_number' => $a->account_number,
            'type'           => $a->type,
            'status'         => $a->status,
            'balance'        => (float) $a->balance,
            'interest_rate'  => (float) $a->interest_rate,
            'target_amount'  => $a->target_amount ? (float) $a->target_amount : null,
            'maturity_date'  => $a->maturity_date?->toDateString(),
            'opened_date'    => $a->opened_date->toDateString(),
            'borrower'       => $a->relationLoaded('borrower') ? [
                'id'              => $a->borrower->id,
                'name'            => $a->borrower->full_name,
                'borrower_number' => $a->borrower->borrower_number,
            ] : ['id' => $a->borrower_id],
        ];
    }

    private function formatTxn(SavingsTransaction $t): array
    {
        return [
            'id'               => $t->id,
            'type'             => $t->type,
            'amount'           => (float) $t->amount,
            'balance_after'    => (float) $t->balance_after,
            'reference'        => $t->reference,
            'notes'            => $t->notes,
            'transaction_date' => $t->transaction_date->toDateString(),
        ];
    }
}

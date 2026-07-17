<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoanStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\Payment;
use App\Services\LoanCalculatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LoanController extends Controller
{
    public function __construct(private LoanCalculatorService $calculator) {}

    public function index(Request $request): Response
    {
        $loans = Loan::query()
            ->with([
                'borrower:id,first_name,last_name,borrower_number,phone',
                'loanType:id,name',
                'loanPlan:id,name',
            ])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('loan_number', 'like', "%{$s}%")
                  ->orWhereHas('borrower', fn ($bq) =>
                      $bq->where('first_name', 'like', "%{$s}%")
                         ->orWhere('last_name', 'like', "%{$s}%")
                         ->orWhere('phone', 'like', "%{$s}%")
                         ->orWhere('borrower_number', 'like', "%{$s}%")
                  );
            }))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->loan_type_id, fn ($q, $id) => $q->where('loan_type_id', $id))
            ->when($request->date_from, fn ($q, $d) => $q->where('application_date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->where('application_date', '<=', $d))
            ->latest('application_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($l) => [
                'id'                  => $l->id,
                'loan_number'         => $l->loan_number,
                'borrower_name'       => $l->borrower->full_name,
                'borrower_number'     => $l->borrower->borrower_number,
                'borrower_phone'      => $l->borrower->phone,
                'loan_type'           => $l->loanType->name,
                'loan_plan'           => $l->loanPlan->name,
                'principal_amount'    => number_format((float) $l->principal_amount, 2),
                'outstanding_balance' => number_format((float) $l->outstanding_balance, 2),
                'status'              => $l->status->value,
                'status_label'        => $l->status->label(),
                'status_color'        => $l->status->color(),
                'application_date'    => $l->application_date->format('d M Y'),
                'disbursement_date'   => $l->disbursement_date?->format('d M Y'),
                'maturity_date'       => $l->maturity_date?->format('d M Y'),
            ]);

        return Inertia::render('loans/Index', [
            'loans'      => $loans,
            'filters'    => $request->only(['search', 'status', 'loan_type_id', 'date_from', 'date_to']),
            'loanTypes'  => LoanType::active()->orderBy('name')->get(['id', 'name']),
            'statuses'   => collect(LoanStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()]),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('loans/Create', [
            'borrower'  => $request->borrower_id
                ? Borrower::findOrFail($request->borrower_id, ['id', 'first_name', 'last_name', 'borrower_number', 'phone', 'is_blacklisted', 'kyc_verified'])
                : null,
            'loanTypes' => LoanType::active()
                ->with(['plans' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'borrower_id'            => ['required', 'exists:borrowers,id'],
            'loan_type_id'           => ['required', 'exists:loan_types,id'],
            'loan_plan_id'           => ['required', 'exists:loan_plans,id'],
            'principal_amount'       => ['required', 'numeric', 'min:0.01'],
            'tenure'                 => ['required', 'integer', 'min:1'],
            'loan_purpose'           => ['nullable', 'string', 'max:1000'],
            'application_date'       => ['required', 'date'],
            'collateral_description' => ['nullable', 'string', 'max:1000'],
            'guarantor_name'         => ['nullable', 'string', 'max:150'],
            'guarantor_phone'        => ['nullable', 'string', 'max:20'],
            'guarantor_relationship' => ['nullable', 'string', 'max:100'],
            'notes'                  => ['nullable', 'string', 'max:2000'],
        ]);

        $plan    = \App\Models\Tenant\LoanPlan::findOrFail($data['loan_plan_id']);
        $amounts = $this->calculator->calculateAmounts($plan, (float) $data['principal_amount'], (int) $data['tenure']);

        $loan = DB::transaction(function () use ($data, $plan, $amounts) {
            $loan = Loan::create([
                ...$data,
                'loan_number'         => $this->generateLoanNumber(),
                'created_by'          => auth()->id(),
                'interest_amount'     => $amounts['interest_amount'],
                'processing_fee'      => $amounts['processing_fee'],
                'insurance_fee'       => $amounts['insurance_fee'],
                'total_payable'       => $amounts['total_payable'],
                'outstanding_balance' => $amounts['total_payable'],
                'interest_rate'       => $plan->interest_rate,
                'interest_type'       => $plan->interest_type,
                'interest_period'     => $plan->interest_period,
                'tenure_type'         => $plan->tenure_type,
                'repayment_schedule'  => $plan->repayment_schedule,
                'penalty_rate'        => $plan->penalty_rate,
                'grace_period_days'   => $plan->grace_period_days,
                'status'              => LoanStatus::Submitted->value,
                'currency'            => tenancy()->tenant?->currency ?? 'ZMW',
            ]);

            $loan->statusLogs()->create([
                'changed_by'  => auth()->id(),
                'from_status' => null,
                'to_status'   => LoanStatus::Submitted->value,
                'notes'       => 'Loan application submitted.',
            ]);

            return $loan;
        });

        return redirect()
            ->route('loans.show', $loan)
            ->with('success', "Loan {$loan->loan_number} created successfully.");
    }

    public function show(Loan $loan): Response
    {
        $loan->load([
            'borrower:id,first_name,last_name,borrower_number,phone,city',
            'loanType:id,name,code',
            'loanPlan:id,name,code',
            'createdBy:id,name',
            'approvedBy:id,name',
            'disbursedBy:id,name',
            'schedule',
            'payments.recordedBy:id,name',
            'statusLogs.changedBy:id,name',
        ]);

        $payments = $loan->payments->map(fn ($p) => [
            'id'              => $p->id,
            'receipt_number'  => $p->receipt_number,
            'amount'          => number_format((float) $p->amount, 2),
            'payment_method'  => $p->payment_method->label(),
            'payment_date'    => $p->payment_date->format('d M Y'),
            'reference'       => $p->reference,
            'recorded_by'     => $p->recordedBy?->name,
        ]);

        $schedule = $loan->schedule->map(fn ($s) => [
            'id'                => $s->id,
            'instalment_number' => $s->instalment_number,
            'due_date'          => $s->due_date->format('d M Y'),
            'total_due'         => number_format((float) $s->total_due, 2),
            'total_paid'        => number_format((float) $s->total_paid, 2),
            'outstanding'       => number_format((float) $s->outstanding, 2),
            'is_paid'           => $s->is_paid,
            'days_overdue'      => $s->days_overdue,
        ]);

        $statusLogs = $loan->statusLogs->map(fn ($log) => [
            'from_status'  => $log->from_status,
            'to_status'    => $log->to_status,
            'to_label'     => LoanStatus::from($log->to_status)->label(),
            'to_color'     => LoanStatus::from($log->to_status)->color(),
            'notes'        => $log->notes,
            'changed_by'   => $log->changedBy?->name,
            'created_at'   => $log->created_at->format('d M Y H:i'),
        ]);

        return Inertia::render('loans/Show', [
            'loan' => [
                'id'                  => $loan->id,
                'loan_number'         => $loan->loan_number,
                'status'              => $loan->status->value,
                'status_label'        => $loan->status->label(),
                'status_color'        => $loan->status->color(),
                'borrower'            => [
                    'id'              => $loan->borrower->id,
                    'name'            => $loan->borrower->full_name,
                    'borrower_number' => $loan->borrower->borrower_number,
                    'phone'           => $loan->borrower->phone,
                    'city'            => $loan->borrower->city,
                ],
                'loan_type'           => $loan->loanType->name,
                'loan_plan'           => $loan->loanPlan->name,
                'principal_amount'    => number_format((float) $loan->principal_amount, 2),
                'interest_amount'     => number_format((float) $loan->interest_amount, 2),
                'processing_fee'      => number_format((float) $loan->processing_fee, 2),
                'insurance_fee'       => number_format((float) $loan->insurance_fee, 2),
                'total_payable'       => number_format((float) $loan->total_payable, 2),
                'total_paid'          => number_format((float) $loan->total_paid, 2),
                'outstanding_balance' => number_format((float) $loan->outstanding_balance, 2),
                'penalty_balance'     => number_format((float) $loan->penalty_balance, 2),
                'interest_rate'       => $loan->interest_rate,
                'interest_type'       => $loan->interest_type,
                'repayment_schedule'  => $loan->repayment_schedule,
                'tenure'              => $loan->tenure,
                'tenure_type'         => $loan->tenure_type,
                'application_date'    => $loan->application_date?->format('d M Y'),
                'approval_date'       => $loan->approval_date?->format('d M Y'),
                'disbursement_date'   => $loan->disbursement_date?->format('d M Y'),
                'first_repayment_date' => $loan->first_repayment_date?->format('d M Y'),
                'maturity_date'       => $loan->maturity_date?->format('d M Y'),
                'disbursement_method' => $loan->disbursement_method?->label(),
                'disbursement_account' => $loan->disbursement_account,
                'loan_purpose'        => $loan->loan_purpose,
                'collateral_description' => $loan->collateral_description,
                'guarantor_name'      => $loan->guarantor_name,
                'guarantor_phone'     => $loan->guarantor_phone,
                'guarantor_relationship' => $loan->guarantor_relationship,
                'notes'               => $loan->notes,
                'created_by'          => $loan->createdBy?->name,
                'approved_by'         => $loan->approvedBy?->name,
                'disbursed_by'        => $loan->disbursedBy?->name,
                'payments'            => $payments,
                'schedule'            => $schedule,
                'status_logs'         => $statusLogs,
            ],
            'can' => [
                'approve'   => auth()->user()?->can('loans.approve'),
                'disburse'  => auth()->user()?->can('loans.disburse'),
                'deny'      => auth()->user()?->can('loans.approve'),
                'freeze'    => auth()->user()?->can('loans.manage'),
                'write_off' => auth()->user()?->can('loans.write_off'),
                'record_payment' => auth()->user()?->can('payments.create'),
                'restructure'   => auth()->user()?->can('loans.manage'),
                'topup'         => auth()->user()?->can('loans.manage'),
            ],
        ]);
    }

    private function generateLoanNumber(): string
    {
        $yearMonth = now()->format('Ym');
        $last = Loan::withTrashed()
            ->where('loan_number', 'like', "LN-{$yearMonth}-%")
            ->max('loan_number');

        $seq = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return 'LN-'.$yearMonth.'-'.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}

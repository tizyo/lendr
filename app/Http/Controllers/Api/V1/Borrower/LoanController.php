<?php

namespace App\Http\Controllers\Api\V1\Borrower;

use App\Enums\LoanStatus;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Jobs\InitiateMobileMoneyPaymentJob;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\MobileMoneyIntent;
use App\Models\Landlord\PlatformBranding;
use App\Services\LoanCalculatorService;
use App\Services\NotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanController extends BaseApiController
{
    public function __construct(
        private LoanCalculatorService $calculator,
        private NotificationService   $notifications,
    ) {}

    /**
     * GET /api/v1/me/loan-products
     * Returns active loan types with their active plans.
     */
    public function products(): JsonResponse
    {
        $types = LoanType::where('is_active', true)
            ->with(['plans' => fn ($q) => $q->where('is_active', true)->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($t) => [
                'id'                  => $t->id,
                'name'                => $t->name,
                'code'                => $t->code,
                'description'         => $t->description,
                'requires_collateral' => $t->requires_collateral,
                'requires_guarantor'  => $t->requires_guarantor,
                'required_documents'  => $t->required_documents ?? [],
                'plans'               => $t->plans->map(fn ($p) => [
                    'id'                 => $p->id,
                    'name'               => $p->name,
                    'code'               => $p->code,
                    'interest_rate'      => (float) $p->interest_rate,
                    'interest_type'      => $p->interest_type,
                    'interest_period'    => $p->interest_period,
                    'min_tenure'         => $p->min_tenure,
                    'max_tenure'         => $p->max_tenure,
                    'tenure_type'        => $p->tenure_type,
                    'min_amount'         => (float) $p->min_amount,
                    'max_amount'         => (float) $p->max_amount,
                    'repayment_schedule' => $p->repayment_schedule,
                    'processing_fee'     => (float) $p->processing_fee,
                    'insurance_fee'      => (float) $p->insurance_fee,
                    'penalty_rate'       => (float) $p->penalty_rate,
                    'grace_period_days'  => $p->grace_period_days,
                ])->values(),
            ]);

        return $this->success($types);
    }

    /**
     * POST /api/v1/me/loans/apply
     * Borrower submits a new loan application.
     */
    public function apply(Request $request): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        if (! $borrower->is_active) {
            return $this->error('Your account is not active. Please contact support.', 403);
        }

        if (! $borrower->kyc_verified) {
            return $this->error('Your identity verification (KYC) is not yet approved. Please submit your documents and wait for review.', 403);
        }

        $data = $request->validate([
            'loan_type_id'           => ['required', 'exists:loan_types,id'],
            'loan_plan_id'           => ['required', 'exists:loan_plans,id'],
            'principal_amount'       => ['required', 'numeric', 'min:0.01'],
            'tenure'                 => ['required', 'integer', 'min:1'],
            'loan_purpose'           => ['nullable', 'string', 'max:1000'],
            'collateral_description' => ['nullable', 'string', 'max:1000'],
            'guarantor_name'         => ['nullable', 'string', 'max:150'],
            'guarantor_phone'        => ['nullable', 'string', 'max:20'],
            'guarantor_relationship' => ['nullable', 'string', 'max:100'],
        ]);

        $plan = LoanPlan::findOrFail($data['loan_plan_id']);

        // Validate plan belongs to the given loan type and is active
        if ((int) $plan->loan_type_id !== (int) $data['loan_type_id'] || ! $plan->is_active) {
            return $this->error('The selected plan is not available for this loan type.', 422);
        }

        // Validate amount against plan limits
        $amount = (float) $data['principal_amount'];
        if ($amount < (float) $plan->min_amount || $amount > (float) $plan->max_amount) {
            return $this->error(
                "Amount must be between {$plan->min_amount} and {$plan->max_amount} {$plan->tenure_type}.",
                422
            );
        }

        // Validate tenure against plan limits
        if ($data['tenure'] < $plan->min_tenure || $data['tenure'] > $plan->max_tenure) {
            return $this->error(
                "Tenure must be between {$plan->min_tenure} and {$plan->max_tenure} {$plan->tenure_type}.",
                422
            );
        }

        $amounts = $this->calculator->calculateAmounts($plan, $amount, (int) $data['tenure']);

        $loan = DB::transaction(function () use ($data, $plan, $amounts, $borrower) {
            $loan = Loan::create([
                'borrower_id'            => $borrower->id,
                'loan_type_id'           => $data['loan_type_id'],
                'loan_plan_id'           => $data['loan_plan_id'],
                'principal_amount'       => $data['principal_amount'],
                'tenure'                 => $data['tenure'],
                'loan_purpose'           => $data['loan_purpose'] ?? null,
                'application_date'       => now()->toDateString(),
                'collateral_description' => $data['collateral_description'] ?? null,
                'guarantor_name'         => $data['guarantor_name'] ?? null,
                'guarantor_phone'        => $data['guarantor_phone'] ?? null,
                'guarantor_relationship' => $data['guarantor_relationship'] ?? null,
                'loan_number'            => $this->generateLoanNumber(),
                'interest_amount'        => $amounts['interest_amount'],
                'processing_fee'         => $amounts['processing_fee'],
                'insurance_fee'          => $amounts['insurance_fee'],
                'total_payable'          => $amounts['total_payable'],
                'outstanding_balance'    => $amounts['total_payable'],
                'interest_rate'          => $plan->interest_rate,
                'interest_type'          => $plan->interest_type,
                'interest_period'        => $plan->interest_period,
                'tenure_type'            => $plan->tenure_type,
                'repayment_schedule'     => $plan->repayment_schedule,
                'penalty_rate'           => $plan->penalty_rate,
                'grace_period_days'      => $plan->grace_period_days,
                'status'                 => LoanStatus::Submitted->value,
                'currency'               => tenancy()->tenant?->currency ?? 'ZMW',
            ]);

            $loan->statusLogs()->create([
                'changed_by'  => null,
                'from_status' => null,
                'to_status'   => LoanStatus::Submitted->value,
                'notes'       => 'Submitted via borrower self-service portal.',
            ]);

            return $loan;
        });

        $loan->load(['loanType:id,name', 'loanPlan:id,name']);

        $this->notifications->notifyRoles(
            ['loan_officer', 'branch_manager', 'super_admin'],
            'loan_submitted',
            "New loan application: {$loan->loan_number}",
            "{$borrower->full_name} applied for ".number_format($amount, 2)." (self-service)",
            ['loan_id' => $loan->id, 'loan_number' => $loan->loan_number],
        );

        return $this->success([
            'id'              => $loan->id,
            'loan_number'     => $loan->loan_number,
            'status'          => $loan->status->value,
            'loan_type'       => $loan->loanType->name,
            'principal'       => number_format((float) $loan->principal_amount, 2),
            'total_payable'   => number_format((float) $loan->total_payable, 2),
            'application_date' => $loan->application_date->toDateString(),
        ], 'Loan application submitted successfully.', 201);
    }

    /**
     * GET /api/v1/me/loans/{id}
     * Returns a specific loan belonging to the authenticated borrower, with repayment schedule.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        $loan = $borrower->loans()
            ->with(['loanType:id,name,code', 'loanPlan:id,name,code', 'schedule'])
            ->findOrFail($id);

        $schedule = $loan->schedule->map(fn ($s) => [
            'instalment_number' => $s->instalment_number,
            'due_date'          => $s->due_date->toDateString(),
            'principal_due'     => (float) $s->principal_due,
            'interest_due'      => (float) $s->interest_due,
            'total_due'         => (float) $s->total_due,
            'total_paid'        => (float) $s->total_paid,
            'outstanding'       => (float) $s->outstanding,
            'is_paid'           => $s->is_paid,
            'paid_date'         => $s->paid_date?->toDateString(),
            'days_overdue'      => $s->days_overdue ?? 0,
            'penalty_accrued'   => (float) ($s->penalty_accrued ?? 0),
        ])->values();

        return $this->success([
            'id'                 => $loan->id,
            'loan_number'        => $loan->loan_number,
            'status'             => $loan->status->value,
            'status_label'       => $loan->status->label(),
            'loan_type'          => $loan->loanType?->name,
            'loan_plan'          => $loan->loanPlan?->name,
            'principal_amount'   => (float) $loan->principal_amount,
            'interest_amount'    => (float) $loan->interest_amount,
            'processing_fee'     => (float) $loan->processing_fee,
            'insurance_fee'      => (float) $loan->insurance_fee,
            'total_payable'      => (float) $loan->total_payable,
            'outstanding_balance' => (float) $loan->outstanding_balance,
            'interest_rate'      => (float) $loan->interest_rate,
            'interest_type'      => $loan->interest_type,
            'repayment_schedule' => $loan->repayment_schedule,
            'tenure'             => $loan->tenure,
            'tenure_type'        => $loan->tenure_type,
            'currency'           => $loan->currency,
            'application_date'   => $loan->application_date?->toDateString(),
            'disbursement_date'  => $loan->disbursement_date?->toDateString(),
            'maturity_date'      => $loan->maturity_date?->toDateString(),
            'schedule'           => $schedule,
        ]);
    }

    /**
     * POST /api/v1/me/loans/{id}/initiate-payment
     * Borrower initiates a mobile money push-payment for their loan.
     */
    public function initiatePayment(Request $request, int $id): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        $loan = $borrower->loans()
            ->whereIn('status', [LoanStatus::Active->value, LoanStatus::Disbursed->value ?? 'disbursed', 'overdue'])
            ->findOrFail($id);

        if ((float) $loan->outstanding_balance <= 0) {
            return $this->error('This loan has no outstanding balance.', 422);
        }

        $data = $request->validate([
            'amount'   => ['required', 'numeric', 'min:1', 'max:' . (float) $loan->outstanding_balance],
            'phone'    => ['required', 'string', 'max:20'],
            'provider' => ['required', 'string', 'in:airtel_money,mtn_momo,zamtel_kwacha,lipila,pawapay'],
        ]);

        // Prevent duplicate pending intents
        $pending = MobileMoneyIntent::where('loan_id', $loan->id)
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->exists();

        if ($pending) {
            return $this->error('A payment is already being processed for this loan. Please wait a moment.', 429);
        }

        $intent = MobileMoneyIntent::create([
            'loan_id'     => $loan->id,
            'borrower_id' => $borrower->id,
            'reference'   => 'PAY-' . strtoupper(Str::random(10)),
            'provider'    => $data['provider'],
            'phone'       => $data['phone'],
            'amount'      => $data['amount'],
            'currency'    => $loan->currency ?? 'ZMW',
            'status'      => 'pending',
            'expires_at'  => now()->addMinutes(15),
        ]);

        dispatch(new InitiateMobileMoneyPaymentJob($intent));

        return $this->success([
            'reference' => $intent->reference,
            'status'    => $intent->status,
            'amount'    => (float) $intent->amount,
            'provider'  => $intent->provider,
            'expires_at'=> $intent->expires_at->toISOString(),
        ], 'Payment request sent. Please approve the prompt on your phone.', 201);
    }

    /**
     * GET /api/v1/me/payment-gateways
     * Returns only the mobile money providers that have credentials configured.
     */
    public function paymentGateways(): JsonResponse
    {
        $available = [];

        $candidates = [
            'airtel_money'  => ['airtel_client_id', 'airtel_client_secret'],
            'mtn_momo'      => ['mtn_subscription_key', 'mtn_api_user', 'mtn_api_key'],
            'zamtel_kwacha' => ['zamtel_api_key', 'zamtel_api_url'],
            'lipila'        => ['lipila_api_key', 'lipila_api_url'],
            'pawapay'       => ['pawapay_api_key'],
        ];

        $labels = [
            'airtel_money'  => 'Airtel Money',
            'mtn_momo'      => 'MTN MoMo',
            'zamtel_kwacha' => 'Zamtel Kwacha',
            'lipila'        => 'Lipila',
            'pawapay'       => 'PawaPay',
        ];

        foreach ($candidates as $provider => $keys) {
            $allSet = collect($keys)->every(
                fn ($k) => filled(DB::table('settings')->where('key', $k)->value('value'))
            );

            if ($allSet) {
                $available[] = ['value' => $provider, 'label' => $labels[$provider]];
            }
        }

        return $this->success($available);
    }

    /**
     * GET /api/v1/me/loans/{id}/payment-status/{reference}
     * Returns the current status of a MobileMoneyIntent for polling.
     */
    public function paymentStatus(Request $request, int $id, string $reference): JsonResponse
    {
        /** @var Borrower $borrower */
        $borrower = $request->user();

        // Ensure loan belongs to this borrower
        $borrower->loans()->findOrFail($id);

        $intent = MobileMoneyIntent::where('loan_id', $id)
            ->where('reference', $reference)
            ->where('borrower_id', $borrower->id)
            ->firstOrFail();

        return $this->success([
            'reference' => $intent->reference,
            'status'    => $intent->status,
            'provider'  => $intent->provider,
            'amount'    => (float) $intent->amount,
            'expires_at'=> $intent->expires_at?->toISOString(),
        ]);
    }

    /**
     * GET /api/v1/me/statement/pdf
     * Download the authenticated borrower's full account statement as PDF.
     */
    public function statementPdf(Request $request): Response
    {
        $borrower = $request->user()->borrower ?? null;
        if (! $borrower) {
            abort(403, 'Borrower profile not found.');
        }

        $borrower->load([
            'loans' => fn ($q) => $q->with(['loanType:id,name', 'payments']),
        ]);

        $currency = tenancy()->tenant?->currency ?? 'ZMW';
        $b        = PlatformBranding::defaults();
        $company  = $b['company_name'];

        $totalDisbursed = 0;
        $totalPaid      = 0;
        $outstanding    = 0;

        $loans = $borrower->loans->map(function ($loan) use (&$totalDisbursed, &$totalPaid, &$outstanding, $currency) {
            $totalDisbursed += (float) $loan->principal_amount;
            $totalPaid      += (float) $loan->total_paid;
            $outstanding    += (float) $loan->outstanding_balance;

            $payments = $loan->payments->map(fn ($p) => [
                'receipt_number'      => $p->receipt_number,
                'payment_date'        => $p->payment_date->format('d M Y'),
                'payment_method'      => $p->payment_method->label(),
                'amount'              => number_format((float) $p->amount, 2),
                'principal_allocated' => number_format((float) $p->principal_allocated, 2),
                'interest_allocated'  => number_format((float) $p->interest_allocated, 2),
            ])->toArray();

            return [
                'loan_number'         => $loan->loan_number,
                'loan_type'           => $loan->loanType->name,
                'status'              => $loan->status->value,
                'status_label'        => $loan->status->label(),
                'currency'            => $currency,
                'principal_amount'    => number_format((float) $loan->principal_amount, 2),
                'total_payable'       => number_format((float) $loan->total_payable, 2),
                'total_paid'          => number_format((float) $loan->total_paid, 2),
                'outstanding_balance' => number_format((float) $loan->outstanding_balance, 2),
                'application_date'    => $loan->application_date?->format('d M Y'),
                'disbursement_date'   => $loan->disbursement_date?->format('d M Y'),
                'maturity_date'       => $loan->maturity_date?->format('d M Y'),
                'payments'            => $payments,
            ];
        })->toArray();

        $pdf = Pdf::loadView('pdf.account-statement', [
            'borrower' => [
                'name'            => $borrower->full_name,
                'borrower_number' => $borrower->borrower_number,
                'phone'           => $borrower->phone,
                'email'           => $borrower->email,
                'city'            => $borrower->city,
                'kyc_verified'    => $borrower->kyc_verified,
                'credit_score'    => $borrower->credit_score,
            ],
            'loans'    => $loans,
            'summary'  => [
                'total_disbursed' => $totalDisbursed,
                'total_paid'      => $totalPaid,
                'outstanding'     => $outstanding,
            ],
            'company'        => $company,
            'address'        => $b['address'],
            'phone'          => $b['phone'],
            'email'          => $b['email'],
            'logo_url'       => $b['logo_url'],
            'invoice_footer' => $b['invoice_footer'],
            'currency'       => $currency,
            'generatedAt'    => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download("statement-{$borrower->borrower_number}.pdf");
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

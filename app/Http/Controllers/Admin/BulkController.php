<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LoanStatus;
use App\Exceptions\LoanActionException;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Services\LoanLifecycleService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BulkController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private LoanLifecycleService $lifecycle,
    ) {}

    // ─── Borrower Import ──────────────────────────────────────────────────────

    public function importBorrowersPage(): Response
    {
        return Inertia::render('bulk/ImportBorrowers', [
            'templateHeaders' => [
                'first_name', 'last_name', 'other_names', 'phone', 'email',
                'gender', 'date_of_birth', 'national_id', 'occupation', 'employer',
                'address', 'city', 'province', 'country',
                'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relationship',
            ],
        ]);
    }

    public function importBorrowers(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');

        if (! $handle) {
            return response()->json(['message' => 'Could not read file.'], 422);
        }

        $headers = array_map('trim', fgetcsv($handle));

        if (! in_array('first_name', $headers) || ! in_array('phone', $headers)) {
            fclose($handle);

            return response()->json(['message' => 'CSV must have at least first_name and phone columns.'], 422);
        }

        $results = ['imported' => 0, 'skipped' => 0, 'errors' => []];
        $row = 0;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;
            if (count($line) < count($headers)) {
                continue;
            }

            $data = array_combine($headers, array_map('trim', $line));

            if (empty($data['first_name']) || empty($data['phone'])) {
                $results['errors'][] = "Row {$row}: first_name and phone are required.";
                $results['skipped']++;

                continue;
            }

            // Skip duplicate phones
            if (Borrower::where('phone', $data['phone'])->exists()) {
                $results['errors'][] = "Row {$row}: Phone {$data['phone']} already registered.";
                $results['skipped']++;

                continue;
            }

            $validator = Validator::make($data, [
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['nullable', 'string', 'max:100'],
                'phone' => ['required', 'string', 'max:20'],
                'email' => ['nullable', 'email', 'max:200'],
            ]);

            if ($validator->fails()) {
                $results['errors'][] = "Row {$row}: ".implode(', ', $validator->errors()->all());
                $results['skipped']++;

                continue;
            }

            try {
                Borrower::create([
                    'borrower_number' => $this->generateBorrowerNumber(),
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'] ?? null,
                    'other_names' => $data['other_names'] ?? null,
                    'phone' => $data['phone'],
                    'email' => ! empty($data['email']) ? $data['email'] : null,
                    'gender' => $data['gender'] ?? null,
                    'date_of_birth' => ! empty($data['date_of_birth']) ? $data['date_of_birth'] : null,
                    'national_id' => $data['national_id'] ?? null,
                    'occupation' => $data['occupation'] ?? null,
                    'employer' => $data['employer'] ?? null,
                    'address' => $data['address'] ?? null,
                    'city' => $data['city'] ?? null,
                    'province' => $data['province'] ?? null,
                    'country' => $data['country'] ?? 'Zambia',
                    'next_of_kin_name' => $data['next_of_kin_name'] ?? null,
                    'next_of_kin_phone' => $data['next_of_kin_phone'] ?? null,
                    'next_of_kin_relationship' => $data['next_of_kin_relationship'] ?? null,
                    'is_active' => true,
                ]);
                $results['imported']++;
            } catch (\Throwable $e) {
                $results['errors'][] = "Row {$row}: ".$e->getMessage();
                $results['skipped']++;
            }
        }

        fclose($handle);

        return response()->json([
            'message' => "Import complete. {$results['imported']} imported, {$results['skipped']} skipped.",
            'results' => $results,
        ]);
    }

    // ─── Bulk Loan Approval ───────────────────────────────────────────────────

    public function bulkLoansPage(): Response
    {
        $submittedLoans = Loan::with(['borrower:id,first_name,last_name,borrower_number', 'loanType:id,name'])
            ->whereIn('status', [LoanStatus::Submitted->value, LoanStatus::Approved->value])
            ->latest()
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'loan_number' => $l->loan_number,
                'borrower_name' => $l->borrower?->full_name,
                'borrower_number' => $l->borrower?->borrower_number,
                'loan_type' => $l->loanType?->name,
                'principal_amount' => number_format((float) $l->principal_amount, 2),
                'status' => $l->status->value,
                'status_label' => $l->status->label(),
                'application_date' => $l->application_date?->format('d M Y'),
            ]);

        return Inertia::render('bulk/BulkLoans', [
            'loans' => $submittedLoans,
            'can' => [
                'approve' => auth()->user()?->can('loans.approve'),
                'disburse' => auth()->user()?->can('loans.disburse'),
            ],
        ]);
    }

    public function bulkApproveLoans(Request $request): JsonResponse
    {
        $request->validate(['loan_ids' => ['required', 'array', 'min:1'], 'loan_ids.*' => ['integer']]);

        $approved = 0;
        $errors = [];

        // The same approval as the loan page: workflows, blacklist and row lock all apply.
        foreach (array_unique($request->loan_ids) as $id) {
            $loan = Loan::find($id);
            if (! $loan) {
                $errors[] = "Loan #{$id} was not found.";

                continue;
            }

            try {
                $this->lifecycle->approve($loan, $request->user(), 'Bulk approved.');
                $approved++;
            } catch (LoanActionException $e) {
                $errors[] = "{$loan->loan_number}: ".$e->getMessage();
            } catch (\Throwable $e) {
                report($e);
                $errors[] = "{$loan->loan_number}: could not be approved (".$e->getMessage().').';
            }
        }

        return response()->json([
            'message' => "{$approved} loan(s) approved.",
            'approved' => $approved,
            'errors' => $errors,
        ]);
    }

    public function bulkDisburseLoans(Request $request): JsonResponse
    {
        $data = $request->validate([
            'loan_ids' => ['required', 'array', 'min:1'],
            'loan_ids.*' => ['integer'],
            'disbursement_method' => ['required', 'in:cash,bank_transfer,airtel_money,mtn_momo,zamtel_kwacha'],
            'disbursement_date' => ['required', 'date'],
        ]);

        $disbursed = 0;
        $errors = [];

        // The same disbursement as the loan page: schedule, fund, GL, payout, CRB,
        // notifications and approval workflows. CRB alerts aren't overridden in bulk;
        // those loans are reported so staff can review and disburse them one by one.
        foreach (array_unique($data['loan_ids']) as $id) {
            $loan = Loan::find($id);
            if (! $loan) {
                $errors[] = "Loan #{$id} was not found.";

                continue;
            }

            try {
                $this->lifecycle->disburse($loan, $request->user(), [
                    'disbursement_method' => $data['disbursement_method'],
                    'disbursement_date' => $data['disbursement_date'],
                    'notes' => 'Bulk disbursed.',
                ]);
                $disbursed++;
            } catch (LoanActionException $e) {
                $errors[] = "{$loan->loan_number}: ".($e->reason === 'crb_alert'
                    ? 'the borrower has active loans at another lender. Open the loan to review and disburse it.'
                    : $e->getMessage());
            } catch (\Throwable $e) {
                report($e);
                $errors[] = "{$loan->loan_number}: could not be disbursed (".$e->getMessage().').';
            }
        }

        return response()->json([
            'message' => "{$disbursed} loan(s) disbursed.",
            'disbursed' => $disbursed,
            'errors' => $errors,
        ]);
    }

    // ─── Batch Payment Upload ─────────────────────────────────────────────────

    public function batchPaymentsPage(): Response
    {
        return Inertia::render('bulk/BatchPayments', [
            'templateHeaders' => ['loan_number', 'amount', 'payment_date', 'payment_method', 'reference', 'notes'],
        ]);
    }

    public function batchPayments(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if (! $handle) {
            return response()->json(['message' => 'Could not read file.'], 422);
        }

        // Excel's UTF-8 export starts with a BOM, which made "loan_number" unrecognisable.
        $headers = array_map(fn ($h) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $h))), fgetcsv($handle) ?: []);

        if (! in_array('loan_number', $headers) || ! in_array('amount', $headers)) {
            fclose($handle);

            return response()->json(['message' => 'CSV must have loan_number and amount columns.'], 422);
        }

        $results = ['processed' => 0, 'skipped' => 0, 'errors' => []];
        $row = 0;

        while (($line = fgetcsv($handle)) !== false) {
            $row++;

            if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }

            if ($row > 2000) {
                $results['errors'][] = 'Stopped at row 2,000: split larger files into several uploads.';
                break;
            }

            // Short rows were skipped without a word and long ones crashed array_combine.
            $line = array_slice(array_pad($line, count($headers), ''), 0, count($headers));
            $data = array_combine($headers, array_map(fn ($v) => trim((string) $v), $line));

            if (empty($data['loan_number']) || $data['amount'] === '') {
                $results['errors'][] = "Row {$row}: loan_number and amount are required.";
                $results['skipped']++;

                continue;
            }

            // "1,000.00" read as (float) used to become 1.
            $rawAmount = str_replace([',', ' '], '', $data['amount']);
            if (! is_numeric($rawAmount)) {
                $results['errors'][] = "Row {$row}: \"{$data['amount']}\" is not an amount.";
                $results['skipped']++;

                continue;
            }
            $data['amount'] = $rawAmount;

            $loan = Loan::where('loan_number', $data['loan_number'])->first();
            if (! $loan) {
                $results['errors'][] = "Row {$row}: Loan {$data['loan_number']} not found.";
                $results['skipped']++;

                continue;
            }

            if (! in_array($loan->status, PaymentService::PAYABLE_STATUSES, true)) {
                $results['errors'][] = "Row {$row}: Loan {$data['loan_number']} isn't taking payments (status: {$loan->status->label()}).";
                $results['skipped']++;

                continue;
            }

            $amount = (float) $data['amount'];
            if ($amount <= 0) {
                $results['errors'][] = "Row {$row}: Amount must be positive.";
                $results['skipped']++;

                continue;
            }

            try {
                // Same checks as a single payment (method, date, duplicate reference,
                // overpayment); "manual", not "migration": these are today's receipts.
                $this->paymentService->record($loan, [
                    'amount' => $amount,
                    'payment_method' => ($data['payment_method'] ?? '') ?: 'cash',
                    'payment_date' => ! empty($data['payment_date']) ? $data['payment_date'] : now()->toDateString(),
                    'recorded_by' => auth()->id(),
                    'reference' => ($data['reference'] ?? '') ?: null,
                    'notes' => ($data['notes'] ?? null) ?: 'Batch import',
                    'source' => 'manual',
                ]);
                $results['processed']++;
            } catch (\Throwable $e) {
                $results['errors'][] = "Row {$row} ({$data['loan_number']}): ".$e->getMessage();
                $results['skipped']++;
            }
        }

        fclose($handle);

        return response()->json([
            'message' => "Batch complete. {$results['processed']} payments processed, {$results['skipped']} skipped.",
            'results' => $results,
        ]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function generateBorrowerNumber(): string
    {
        $prefix = 'BRW-'.now()->format('Ym').'-';
        $last = Borrower::where('borrower_number', 'like', $prefix.'%')->max('borrower_number');
        $seq = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}

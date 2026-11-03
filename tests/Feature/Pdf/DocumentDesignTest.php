<?php

use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\Payment;
use App\Support\DocumentBrand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

/*
 * Every PDF shares one branded layout (resources/views/pdf/layout): the lender's
 * own name, contact and registration details, logo and colour, and a small
 * "Powered by LENDR" footer, instead of the platform's details.
 */

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(fn () => Tenant::query()->where('id', 'like', 'pdfbrand-%')->each(fn ($t) => $t->delete()));
});

function pdfDesignLoan(array $attrs = []): Loan
{
    $type = LoanType::first() ?? LoanType::factory()->create();
    $plan = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);

    return Loan::factory()->create([
        'borrower_id' => Borrower::factory()->create()->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        ...$attrs,
    ]);
}

it('downloads every document as a real PDF', function () {
    $admin = actingAsAdmin();
    $loan = pdfDesignLoan(['status' => LoanStatus::Active, 'disbursement_date' => now()->toDateString()]);
    $payment = Payment::factory()->create(['loan_id' => $loan->id, 'recorded_by' => $admin->id, 'amount' => 500, 'payment_method' => PaymentMethod::Cash]);

    foreach ([
        route('loans.pdf.application', $loan),
        route('loans.pdf.agreement', $loan),
        route('loans.pdf.schedule', $loan),
        route('loans.pdf.disbursement', $loan),
        route('payments.pdf.receipt', $payment),
        route('borrowers.pdf.statement', $loan->borrower),
    ] as $url) {
        $response = $this->actingAs($admin)->get($url);
        $response->assertOk();
        expect($response->headers->get('content-type'))->toContain('application/pdf')
            ->and(substr($response->getContent(), 0, 5))->toBe('%PDF-');
    }
});

it('projects the schedule on an agreement for a loan not yet disbursed', function () {
    $loan = pdfDesignLoan(['status' => LoanStatus::Approved, 'disbursement_date' => null]);
    expect($loan->schedule()->count())->toBe(0);

    $captured = null;
    View::composer('pdf.loan-agreement', function ($view) use (&$captured) {
        $captured = $view->getData()['loan'];
    });

    $this->actingAs(actingAsAdmin())->get(route('loans.pdf.agreement', $loan))->assertOk();

    expect($captured['schedule_projected'])->toBeTrue()
        ->and($captured['schedule'])->not->toBeEmpty()
        ->and($captured['borrower'])->toHaveKey('kyc_verified');
});

it('uses the real schedule once the loan is disbursed', function () {
    $loan = pdfDesignLoan(['status' => LoanStatus::Active, 'disbursement_date' => now()->toDateString()]);
    LoanSchedule::create(['loan_id' => $loan->id, 'instalment_number' => 1, 'due_date' => now()->addMonth(),
        'principal_due' => 100, 'interest_due' => 10, 'fee_due' => 0, 'total_due' => 110, 'outstanding' => 110]);

    $captured = null;
    View::composer('pdf.loan-agreement', function ($view) use (&$captured) {
        $captured = $view->getData()['loan'];
    });

    $this->actingAs(actingAsAdmin())->get(route('loans.pdf.agreement', $loan))->assertOk();

    expect($captured['schedule_projected'])->toBeFalse()
        ->and($captured['schedule'])->toHaveCount(1);
});

it('brands documents with the lender\'s own details', function () {
    config(['tenancy.bootstrappers' => []]);
    $tenant = Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'pdfbrand-'.uniqid(), 'name' => 'Cashplus', 'slug' => 'cashplus-'.uniqid(), 'plan' => 'growth',
        'status' => 'active', 'currency' => 'ZMW', 'timezone' => 'Africa/Lusaka', 'email_verified_at' => now(),
    ]));
    tenancy()->initialize($tenant);
    foreach ([
        'company_name' => 'Cashplus Financial Services',
        'company_registration_number' => '120200012345',
        'company_licence_number' => 'BOZ/ML/0421',
        'document_footer' => 'Licensed by the Bank of Zambia',
        'primary_color' => '#16A34A',
    ] as $key => $value) {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value]);
    }

    $brand = DocumentBrand::current();
    expect($brand['name'])->toBe('Cashplus Financial Services')
        ->and($brand['registration'])->toBe(['PACRA Reg. No.' => '120200012345', 'Licence No.' => 'BOZ/ML/0421'])
        ->and($brand['color'])->toBe('#16A34A');

    $html = view('pdf.payment-receipt', [
        'payment' => ['receipt_number' => 'REC-1', 'loan_number' => 'LN-1', 'borrower_name' => 'A Banda', 'borrower_number' => 'BOR-1',
            'loan_type' => 'Personal', 'amount' => '500.00', 'principal_allocated' => '100.00', 'interest_allocated' => '400.00',
            'penalty_allocated' => '0.00', 'payment_method' => 'Cash', 'payment_date' => '03 Nov 2026', 'reference' => null,
            'notes' => null, 'recorded_by' => 'Staff'],
        'currency' => 'ZMW',
    ])->render();

    expect($html)->toContain('Cashplus Financial Services')
        ->toContain('PACRA Reg. No.: 120200012345')
        ->toContain('Licensed by the Bank of Zambia')
        ->toContain('Powered by <strong>LENDR</strong>')
        ->toContain('<title>Payment Receipt | Receipt REC-1</title>');
});

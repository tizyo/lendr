<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — LENDR
|--------------------------------------------------------------------------
| All routes prefixed with /api/v1
| Staff auth: Laravel Sanctum (Bearer token)
| Borrower auth: Sanctum (OTP/PIN — borrower tokens)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ─── Staff Auth (public) ──────────────────────────
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login',           [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'login'])->name('login');
        Route::post('forgot-password', [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password',  [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'resetPassword'])->name('reset-password');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me',             [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'me'])->name('me');
            Route::post('logout',        [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'logout'])->name('logout');
            Route::post('refresh',       [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'refresh'])->name('refresh');
            Route::post('2fa/setup',     [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'setup2fa'])->name('2fa.setup');
            Route::post('2fa/verify',    [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'verify2fa'])->name('2fa.verify');
            Route::post('2fa/challenge', [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'challenge2fa'])->name('2fa.challenge');
            Route::delete('2fa',         [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'disable2fa'])->name('2fa.disable');
        });
    });

    // ─── Borrower Auth (public) ───────────────────────
    Route::prefix('borrower/auth')->name('borrower.auth.')->group(function () {
        Route::post('request-otp', [\App\Http\Controllers\Api\V1\Auth\BorrowerAuthController::class, 'requestOtp'])->name('request-otp');
        Route::post('verify-otp',  [\App\Http\Controllers\Api\V1\Auth\BorrowerAuthController::class, 'verifyOtp'])->name('verify-otp');
        Route::post('login-pin',   [\App\Http\Controllers\Api\V1\Auth\BorrowerAuthController::class, 'loginPin'])->name('login-pin');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('set-pin', [\App\Http\Controllers\Api\V1\Auth\BorrowerAuthController::class, 'setPin'])->name('set-pin');
            Route::post('refresh', [\App\Http\Controllers\Api\V1\Auth\BorrowerAuthController::class, 'refreshToken'])->name('refresh');
        });
    });

    // ─── Protected Staff Routes (Sanctum) ─────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Borrowers
        Route::apiResource('borrowers', \App\Http\Controllers\Api\V1\BorrowerController::class)->only(['index', 'show'])->middleware('permission:borrowers.view');
        Route::apiResource('borrowers', \App\Http\Controllers\Api\V1\BorrowerController::class)->only(['store'])->middleware('permission:borrowers.create');
        Route::apiResource('borrowers', \App\Http\Controllers\Api\V1\BorrowerController::class)->only(['update'])->middleware('permission:borrowers.edit');
        Route::apiResource('borrowers', \App\Http\Controllers\Api\V1\BorrowerController::class)->only(['destroy'])->middleware('permission:borrowers.delete');
        Route::get('borrowers/{borrower}/loans',          [\App\Http\Controllers\Api\V1\BorrowerController::class, 'loans'])->name('borrowers.loans')->middleware('permission:borrowers.view');
        Route::get('borrowers/{borrower}/statement',      [\App\Http\Controllers\Api\V1\BorrowerController::class, 'statement'])->name('borrowers.statement')->middleware('permission:borrowers.view');
        Route::get('borrowers/{borrower}/notes',          [\App\Http\Controllers\Api\V1\BorrowerController::class, 'notes'])->name('borrowers.notes')->middleware('permission:borrowers.view');
        Route::post('borrowers/{borrower}/notes',         [\App\Http\Controllers\Api\V1\BorrowerController::class, 'addNote'])->name('borrowers.notes.store')->middleware('permission:borrowers.edit');
        Route::post('borrowers/{borrower}/blacklist',     [\App\Http\Controllers\Api\V1\BorrowerController::class, 'toggleBlacklist'])->name('borrowers.blacklist')->middleware('permission:borrowers.blacklist');

        // KYC
        Route::get('kyc/pending',                              [\App\Http\Controllers\Api\V1\KycController::class, 'pending'])->name('kyc.pending')->middleware('permission:kyc.view');
        Route::post('borrowers/{borrower}/kyc/documents',      [\App\Http\Controllers\Api\V1\KycController::class, 'upload'])->name('kyc.upload')->middleware('permission:kyc.upload');
        Route::get('borrowers/{borrower}/kyc/documents',       [\App\Http\Controllers\Api\V1\KycController::class, 'borrowerDocuments'])->name('kyc.borrower-documents')->middleware('permission:kyc.view');
        Route::post('kyc/{document}/start-review',             [\App\Http\Controllers\Api\V1\KycController::class, 'startReview'])->name('kyc.start-review')->middleware('permission:kyc.review');
        Route::put('kyc/{document}/review',                    [\App\Http\Controllers\Api\V1\KycController::class, 'review'])->name('kyc.review')->middleware('permission:kyc.review');
        Route::get('kyc/{document}/view',                      [\App\Http\Controllers\Api\V1\KycController::class, 'view'])->name('kyc.view')->middleware('permission:kyc.view');
        Route::delete('kyc/{document}',                        [\App\Http\Controllers\Api\V1\KycController::class, 'destroy'])->name('kyc.destroy')->middleware('permission:kyc.review');

        // Loan Products
        Route::apiResource('loan-types', \App\Http\Controllers\Api\V1\LoanTypeController::class)->only(['index', 'show'])->middleware('permission:loan_products.view');
        Route::apiResource('loan-types', \App\Http\Controllers\Api\V1\LoanTypeController::class)->only(['store'])->middleware('permission:loan_products.create');
        Route::apiResource('loan-types', \App\Http\Controllers\Api\V1\LoanTypeController::class)->only(['update'])->middleware('permission:loan_products.edit');
        Route::apiResource('loan-types', \App\Http\Controllers\Api\V1\LoanTypeController::class)->only(['destroy'])->middleware('permission:loan_products.delete');
        Route::apiResource('loan-plans', \App\Http\Controllers\Api\V1\LoanPlanController::class)->only(['index', 'show'])->middleware('permission:loan_products.view');
        Route::apiResource('loan-plans', \App\Http\Controllers\Api\V1\LoanPlanController::class)->only(['store'])->middleware('permission:loan_products.create');
        Route::apiResource('loan-plans', \App\Http\Controllers\Api\V1\LoanPlanController::class)->only(['update'])->middleware('permission:loan_products.edit');
        Route::apiResource('loan-plans', \App\Http\Controllers\Api\V1\LoanPlanController::class)->only(['destroy'])->middleware('permission:loan_products.delete');
        Route::get('loan-plans/{plan}/calculate', [\App\Http\Controllers\Api\V1\LoanPlanController::class, 'calculate'])->name('loan-plans.calculate')->middleware('permission:loan_products.view');

        // Loans
        Route::apiResource('loans', \App\Http\Controllers\Api\V1\LoanController::class)->only(['index', 'show'])->middleware('permission:loans.view');
        Route::apiResource('loans', \App\Http\Controllers\Api\V1\LoanController::class)->only(['store'])->middleware('permission:loans.create');
        Route::apiResource('loans', \App\Http\Controllers\Api\V1\LoanController::class)->only(['update'])->middleware('permission:loans.edit');
        Route::apiResource('loans', \App\Http\Controllers\Api\V1\LoanController::class)->only(['destroy'])->middleware('permission:loans.delete');
        Route::post('loans/{loan}/submit',      [\App\Http\Controllers\Api\V1\LoanController::class, 'submit'])->name('loans.submit')->middleware('permission:loans.edit');
        Route::post('loans/{loan}/review',      [\App\Http\Controllers\Api\V1\LoanController::class, 'review'])->name('loans.review')->middleware('permission:loans.edit');
        Route::post('loans/{loan}/approve',     [\App\Http\Controllers\Api\V1\LoanController::class, 'approve'])->name('loans.approve')->middleware('permission:loans.approve');
        Route::post('loans/{loan}/disburse',    [\App\Http\Controllers\Api\V1\LoanController::class, 'disburse'])->name('loans.disburse')->middleware('permission:loans.disburse');
        Route::post('loans/{loan}/deny',        [\App\Http\Controllers\Api\V1\LoanController::class, 'deny'])->name('loans.deny')->middleware('permission:loans.deny');
        Route::post('loans/{loan}/freeze',      [\App\Http\Controllers\Api\V1\LoanController::class, 'freeze'])->name('loans.freeze')->middleware('permission:loans.freeze');
        Route::post('loans/{loan}/unfreeze',    [\App\Http\Controllers\Api\V1\LoanController::class, 'unfreeze'])->name('loans.unfreeze')->middleware('permission:loans.freeze');
        Route::post('loans/{loan}/write-off',   [\App\Http\Controllers\Api\V1\LoanController::class, 'writeOff'])->name('loans.write-off')->middleware('permission:loans.write_off');
        Route::post('loans/{loan}/restructure', [\App\Http\Controllers\Api\V1\LoanController::class, 'restructure'])->name('loans.restructure')->middleware('permission:loans.edit');
        // Standing Orders (Phase 55)
        Route::get('loans/{loan}/standing-orders',     [\App\Http\Controllers\Api\V1\StandingOrderController::class, 'index'])->name('loans.standing-orders.index');
        Route::patch('standing-orders/{order}/cancel', [\App\Http\Controllers\Api\V1\StandingOrderController::class, 'cancel'])->name('standing-orders.cancel');
        // Loan Top-ups (Phase 53)
        Route::get('loans/{loan}/topups',                  [\App\Http\Controllers\Api\V1\LoanTopupController::class, 'index'])->name('loans.topups.index');
        Route::post('loans/{loan}/topups',                 [\App\Http\Controllers\Api\V1\LoanTopupController::class, 'store'])->name('loans.topups.store');
        Route::post('loans/{loan}/topups/{topup}/approve', [\App\Http\Controllers\Api\V1\LoanTopupController::class, 'approve'])->name('loans.topups.approve');
        Route::post('loans/{loan}/topups/{topup}/reject',  [\App\Http\Controllers\Api\V1\LoanTopupController::class, 'reject'])->name('loans.topups.reject');
        // Write-off & Recovery (Phase 29)
        Route::get('loans/{loan}/writeoff',     [\App\Http\Controllers\Api\V1\WriteoffController::class, 'show'])->name('loans.writeoff.show')->middleware('permission:loans.view');
        Route::post('loans/{loan}/recovery',    [\App\Http\Controllers\Api\V1\WriteoffController::class, 'recovery'])->name('loans.writeoff.recovery')->middleware('permission:loans.write_off');
        Route::get('writeoffs',                 [\App\Http\Controllers\Api\V1\WriteoffController::class, 'index'])->name('writeoffs.index')->middleware('permission:loans.view');
        Route::get('loans/{loan}/schedule',     [\App\Http\Controllers\Api\V1\LoanController::class, 'schedule'])->name('loans.schedule')->middleware('permission:loans.view');
        Route::get('loans/{loan}/payments',     [\App\Http\Controllers\Api\V1\PaymentController::class, 'byLoan'])->name('loans.payments')->middleware('permission:loans.view');
        // Loan documents
        Route::get('loans/{loan}/documents',    [\App\Http\Controllers\Api\V1\LoanDocumentController::class, 'index'])->name('loans.documents.index')->middleware('permission:loans.view');
        Route::post('loans/{loan}/documents',   [\App\Http\Controllers\Api\V1\LoanDocumentController::class, 'store'])->name('loans.documents.store')->middleware('permission:loans.edit');
        Route::delete('loans/{loan}/documents/{document}', [\App\Http\Controllers\Api\V1\LoanDocumentController::class, 'destroy'])->name('loans.documents.destroy')->middleware('permission:loans.edit');
        // Loan guarantors
        Route::get('loans/{loan}/guarantors',         [\App\Http\Controllers\Api\V1\GuarantorController::class, 'index'])->name('loans.guarantors.index')->middleware('permission:loans.view');
        Route::post('loans/{loan}/guarantors',        [\App\Http\Controllers\Api\V1\GuarantorController::class, 'store'])->name('loans.guarantors.store')->middleware('permission:loans.edit');
        Route::get('guarantors/{guarantor}',          [\App\Http\Controllers\Api\V1\GuarantorController::class, 'show'])->name('guarantors.show')->middleware('permission:loans.view');
        Route::put('guarantors/{guarantor}',          [\App\Http\Controllers\Api\V1\GuarantorController::class, 'update'])->name('guarantors.update')->middleware('permission:loans.edit');
        Route::delete('guarantors/{guarantor}',       [\App\Http\Controllers\Api\V1\GuarantorController::class, 'destroy'])->name('guarantors.destroy')->middleware('permission:loans.edit');
        // Loan collateral
        Route::get('loans/{loan}/collateral',         [\App\Http\Controllers\Api\V1\CollateralController::class, 'index'])->name('loans.collateral.index')->middleware('permission:loans.view');
        Route::post('loans/{loan}/collateral',        [\App\Http\Controllers\Api\V1\CollateralController::class, 'store'])->name('loans.collateral.store')->middleware('permission:loans.edit');
        Route::get('collateral/{collateral}',         [\App\Http\Controllers\Api\V1\CollateralController::class, 'show'])->name('collateral.show')->middleware('permission:loans.view');
        Route::put('collateral/{collateral}',         [\App\Http\Controllers\Api\V1\CollateralController::class, 'update'])->name('collateral.update')->middleware('permission:loans.edit');
        Route::delete('collateral/{collateral}',      [\App\Http\Controllers\Api\V1\CollateralController::class, 'destroy'])->name('collateral.destroy')->middleware('permission:loans.edit');
        // Loan insurance
        Route::get('insurance/products',                       [\App\Http\Controllers\Api\V1\InsuranceController::class, 'products'])->name('insurance.products.index');
        Route::post('insurance/products',                      [\App\Http\Controllers\Api\V1\InsuranceController::class, 'storeProduct'])->name('insurance.products.store');
        Route::put('insurance/products/{product}',             [\App\Http\Controllers\Api\V1\InsuranceController::class, 'updateProduct'])->name('insurance.products.update');
        Route::delete('insurance/products/{product}',          [\App\Http\Controllers\Api\V1\InsuranceController::class, 'destroyProduct'])->name('insurance.products.destroy');
        Route::get('loans/{loan}/insurance',                   [\App\Http\Controllers\Api\V1\InsuranceController::class, 'loanPolicies'])->name('loans.insurance.index');
        Route::post('loans/{loan}/insurance',                  [\App\Http\Controllers\Api\V1\InsuranceController::class, 'attachPolicy'])->name('loans.insurance.attach');
        Route::get('insurance/policies',                        [\App\Http\Controllers\Api\V1\InsuranceController::class, 'allPolicies'])->name('insurance.policies.index');
        Route::get('insurance/claims',                         [\App\Http\Controllers\Api\V1\InsuranceController::class, 'allClaims'])->name('insurance.claims.index');
        Route::put('insurance/policies/{policy}',              [\App\Http\Controllers\Api\V1\InsuranceController::class, 'updatePolicy'])->name('insurance.policies.update');
        Route::get('insurance/policies/{policy}/claims',       [\App\Http\Controllers\Api\V1\InsuranceController::class, 'policyClaims'])->name('insurance.policies.claims');
        Route::post('insurance/policies/{policy}/claims',      [\App\Http\Controllers\Api\V1\InsuranceController::class, 'fileClaim'])->name('insurance.policies.claims.store');
        Route::put('insurance/claims/{claim}/review',          [\App\Http\Controllers\Api\V1\InsuranceController::class, 'reviewClaim'])->name('insurance.claims.review');
        // Investors
        Route::get('investors/portfolio',                      [\App\Http\Controllers\Api\V1\InvestorController::class, 'portfolio'])->name('investors.portfolio');
        Route::apiResource('investors', \App\Http\Controllers\Api\V1\InvestorController::class)->names('investors');
        Route::get('investors/{investor}/allocations',         [\App\Http\Controllers\Api\V1\InvestorController::class, 'allocations'])->name('investors.allocations');
        Route::post('investors/{investor}/allocations',        [\App\Http\Controllers\Api\V1\InvestorController::class, 'allocate'])->name('investors.allocate');
        Route::put('investor-allocations/{allocation}',        [\App\Http\Controllers\Api\V1\InvestorController::class, 'updateAllocation'])->name('investor-allocations.update');

        // ─── Investor Returns / Dividends (Phase 67) ──────────────────────────
        Route::get('investors/{investor}/dividends',           [\App\Http\Controllers\Api\V1\InvestorReturnsController::class, 'index'])->name('investors.dividends.index');
        Route::post('investors/{investor}/dividends',          [\App\Http\Controllers\Api\V1\InvestorReturnsController::class, 'calculate'])->name('investors.dividends.calculate');
        Route::post('investor-dividends/{dividend}/pay',       [\App\Http\Controllers\Api\V1\InvestorReturnsController::class, 'pay'])->name('investor-dividends.pay');
        Route::delete('investor-dividends/{dividend}',         [\App\Http\Controllers\Api\V1\InvestorReturnsController::class, 'cancel'])->name('investor-dividends.cancel');
        // IFRS9 Provisioning
        Route::get('provisioning/rates',                       [\App\Http\Controllers\Api\V1\ProvisioningController::class, 'rates'])->name('provisioning.rates.index');
        Route::post('provisioning/rates',                      [\App\Http\Controllers\Api\V1\ProvisioningController::class, 'storeRate'])->name('provisioning.rates.store');
        Route::put('provisioning/rates/{rate}',                [\App\Http\Controllers\Api\V1\ProvisioningController::class, 'updateRate'])->name('provisioning.rates.update');
        Route::post('provisioning/rates/seed',                 [\App\Http\Controllers\Api\V1\ProvisioningController::class, 'seedRates'])->name('provisioning.rates.seed');
        Route::post('provisioning/run',                        [\App\Http\Controllers\Api\V1\ProvisioningController::class, 'runPortfolio'])->name('provisioning.run');
        Route::get('provisioning/summary',                     [\App\Http\Controllers\Api\V1\ProvisioningController::class, 'portfolioSummary'])->name('provisioning.summary');
        Route::post('loans/{loan}/provision',                  [\App\Http\Controllers\Api\V1\ProvisioningController::class, 'calculateLoan'])->name('loans.provision');
        Route::get('loans/{loan}/provisions',                  [\App\Http\Controllers\Api\V1\ProvisioningController::class, 'loanHistory'])->name('loans.provisions');
        // Interest Accrual
        Route::get('interest-accrual',                         [\App\Http\Controllers\Api\V1\InterestAccrualController::class, 'index'])->name('interest-accrual.index');
        Route::post('interest-accrual/run',                    [\App\Http\Controllers\Api\V1\InterestAccrualController::class, 'run'])->name('interest-accrual.run');
        Route::get('interest-accrual/summary',                 [\App\Http\Controllers\Api\V1\InterestAccrualController::class, 'summary'])->name('interest-accrual.summary');
        // Penalties
        Route::get('penalties',                                [\App\Http\Controllers\Api\V1\PenaltyController::class, 'index'])->name('penalties.index');
        Route::post('penalties/run',                           [\App\Http\Controllers\Api\V1\PenaltyController::class, 'run'])->name('penalties.run');
        Route::post('penalties/{penalty}/waive',               [\App\Http\Controllers\Api\V1\PenaltyController::class, 'waive'])->name('penalties.waive');
        Route::get('loans/{loan}/penalties',                   [\App\Http\Controllers\Api\V1\PenaltyController::class, 'loanPenalties'])->name('loans.penalties');
        // Financial Statements
        Route::prefix('financial-statements')->name('financial-statements.')->group(function () {
            Route::get('balance-sheet',    [\App\Http\Controllers\Api\V1\FinancialStatementController::class, 'balanceSheet'])->name('balance-sheet');
            Route::get('income-statement', [\App\Http\Controllers\Api\V1\FinancialStatementController::class, 'incomeStatement'])->name('income-statement');
            Route::get('cash-flow',        [\App\Http\Controllers\Api\V1\FinancialStatementController::class, 'cashFlow'])->name('cash-flow');
            Route::get('par',              [\App\Http\Controllers\Api\V1\FinancialStatementController::class, 'portfolioAtRisk'])->name('par');
        });
        // Approval Workflows
        Route::get('approvals/workflows',                      [\App\Http\Controllers\Api\V1\ApprovalController::class, 'indexWorkflows'])->name('approvals.workflows.index');
        Route::post('approvals/workflows',                     [\App\Http\Controllers\Api\V1\ApprovalController::class, 'storeWorkflow'])->name('approvals.workflows.store');
        Route::put('approvals/workflows/{workflow}',           [\App\Http\Controllers\Api\V1\ApprovalController::class, 'updateWorkflow'])->name('approvals.workflows.update');
        Route::get('approvals/pending',                        [\App\Http\Controllers\Api\V1\ApprovalController::class, 'pending'])->name('approvals.pending');
        Route::post('approvals/submit',                        [\App\Http\Controllers\Api\V1\ApprovalController::class, 'submit'])->name('approvals.submit');
        Route::get('approvals/{approvalRequest}',                      [\App\Http\Controllers\Api\V1\ApprovalController::class, 'show'])->name('approvals.show');
        Route::post('approvals/{approvalRequest}/approve',             [\App\Http\Controllers\Api\V1\ApprovalController::class, 'approve'])->name('approvals.approve');
        Route::post('approvals/{approvalRequest}/reject',              [\App\Http\Controllers\Api\V1\ApprovalController::class, 'reject'])->name('approvals.reject');

        // Payments
        Route::apiResource('payments', \App\Http\Controllers\Api\V1\PaymentController::class)->only(['index', 'show'])->middleware('permission:payments.view');
        Route::apiResource('payments', \App\Http\Controllers\Api\V1\PaymentController::class)->only(['store'])->middleware('permission:payments.create');
        Route::apiResource('payments', \App\Http\Controllers\Api\V1\PaymentController::class)->only(['destroy'])->middleware('permission:payments.delete');
        Route::get('payments/{payment}/receipt', [\App\Http\Controllers\Api\V1\PaymentController::class, 'receipt'])->name('payments.receipt')->middleware('permission:payments.view');

        // Fund Management
        Route::middleware('permission:funds.view')->group(function () {
            Route::get('funds/balance',      [\App\Http\Controllers\Api\V1\FundController::class, 'balance'])->name('funds.balance');
            Route::get('funds/summary',      [\App\Http\Controllers\Api\V1\FundController::class, 'summary'])->name('funds.summary');
            Route::get('funds/transactions', [\App\Http\Controllers\Api\V1\FundController::class, 'transactions'])->name('funds.transactions');
        });
        Route::apiResource('funds/deposits', \App\Http\Controllers\Api\V1\FundDepositController::class)->names('funds.deposits')->only(['index', 'show'])->middleware('permission:funds.view');
        Route::apiResource('funds/deposits', \App\Http\Controllers\Api\V1\FundDepositController::class)->names('funds.deposits')->only(['store'])->middleware('permission:funds.deposit');
        Route::apiResource('funds/deposits', \App\Http\Controllers\Api\V1\FundDepositController::class)->names('funds.deposits')->only(['update', 'destroy'])->middleware('permission:funds.approve_deposit');
        Route::post('funds/deposits/{deposit}/approve', [\App\Http\Controllers\Api\V1\FundDepositController::class, 'approve'])->name('funds.deposits.approve')->middleware('permission:funds.approve_deposit');
        Route::post('funds/deposits/{deposit}/reject',  [\App\Http\Controllers\Api\V1\FundDepositController::class, 'reject'])->name('funds.deposits.reject')->middleware('permission:funds.approve_deposit');

        // Expenses
        Route::apiResource('expenses', \App\Http\Controllers\Api\V1\ExpenseController::class)->only(['index', 'show'])->middleware('permission:expenses.view');
        Route::apiResource('expenses', \App\Http\Controllers\Api\V1\ExpenseController::class)->only(['store'])->middleware('permission:expenses.create');
        Route::apiResource('expenses', \App\Http\Controllers\Api\V1\ExpenseController::class)->only(['update'])->middleware('permission:expenses.edit');
        Route::apiResource('expenses', \App\Http\Controllers\Api\V1\ExpenseController::class)->only(['destroy'])->middleware('permission:expenses.delete');
        Route::post('expenses/{expense}/submit',  [\App\Http\Controllers\Api\V1\ExpenseController::class, 'submit'])->name('expenses.submit')->middleware('permission:expenses.edit');
        Route::post('expenses/{expense}/approve', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'approve'])->name('expenses.approve')->middleware('permission:expenses.approve');
        Route::post('expenses/{expense}/reject',  [\App\Http\Controllers\Api\V1\ExpenseController::class, 'reject'])->name('expenses.reject')->middleware('permission:expenses.approve');
        Route::apiResource('expense-categories', \App\Http\Controllers\Api\V1\ExpenseCategoryController::class)->middleware('permission:expenses.view');
        Route::get('expense-budgets',                    [\App\Http\Controllers\Api\V1\ExpenseCategoryController::class, 'budgets'])->name('expense-budgets.index')->middleware('permission:expenses.view');
        Route::post('expense-budgets',                   [\App\Http\Controllers\Api\V1\ExpenseCategoryController::class, 'storeBudget'])->name('expense-budgets.store')->middleware('permission:expenses.edit');
        Route::get('expense-settings',                   [\App\Http\Controllers\Api\V1\ExpenseCategoryController::class, 'settings'])->name('expense-settings.show')->middleware('permission:expenses.view');
        Route::put('expense-settings',                   [\App\Http\Controllers\Api\V1\ExpenseCategoryController::class, 'updateSettings'])->name('expense-settings.update')->middleware('permission:expenses.edit');
        Route::get('exchange-rates/current',             [\App\Http\Controllers\Api\V1\ExchangeRateController::class, 'current'])->name('exchange-rates.current');
        Route::apiResource('exchange-rates',             \App\Http\Controllers\Api\V1\ExchangeRateController::class)->except(['show']);

        // ─── Multi-Currency (Phase 64) ────────────────────────────────────────
        Route::get('multi-currency/portfolio',           [\App\Http\Controllers\Api\V1\MultiCurrencyController::class, 'portfolio'])->name('multi-currency.portfolio');
        Route::post('multi-currency/convert',            [\App\Http\Controllers\Api\V1\MultiCurrencyController::class, 'convert'])->name('multi-currency.convert');
        Route::get('loans/{loan}/currency',              [\App\Http\Controllers\Api\V1\MultiCurrencyController::class, 'loanInfo'])->name('loans.currency');

        // ─── Auto Credit Decision (Phase 66) ─────────────────────────────────
        Route::get('auto-decision/rules',                [\App\Http\Controllers\Api\V1\AutoDecisionController::class, 'rules'])->name('auto-decision.rules.index');
        Route::post('auto-decision/rules',               [\App\Http\Controllers\Api\V1\AutoDecisionController::class, 'storeRule'])->name('auto-decision.rules.store');
        Route::put('auto-decision/rules/{rule}',         [\App\Http\Controllers\Api\V1\AutoDecisionController::class, 'updateRule'])->name('auto-decision.rules.update');
        Route::delete('auto-decision/rules/{rule}',      [\App\Http\Controllers\Api\V1\AutoDecisionController::class, 'destroyRule'])->name('auto-decision.rules.destroy');
        Route::post('auto-decision/evaluate/{loan}',     [\App\Http\Controllers\Api\V1\AutoDecisionController::class, 'evaluate'])->name('auto-decision.evaluate');
        Route::get('auto-decision/{loan}',               [\App\Http\Controllers\Api\V1\AutoDecisionController::class, 'show'])->name('auto-decision.show');
        Route::post('auto-decision/{decision}/override', [\App\Http\Controllers\Api\V1\AutoDecisionController::class, 'override'])->name('auto-decision.override');

        // ─── Compliance Calendar (Phase 68) ───────────────────────────────────
        Route::get('compliance-events/upcoming',                  [\App\Http\Controllers\Api\V1\ComplianceCalendarController::class, 'upcoming'])->name('compliance-events.upcoming');
        Route::get('compliance-events',                           [\App\Http\Controllers\Api\V1\ComplianceCalendarController::class, 'index'])->name('compliance-events.index');
        Route::post('compliance-events',                          [\App\Http\Controllers\Api\V1\ComplianceCalendarController::class, 'store'])->name('compliance-events.store');
        Route::get('compliance-events/{complianceEvent}',         [\App\Http\Controllers\Api\V1\ComplianceCalendarController::class, 'show'])->name('compliance-events.show');
        Route::put('compliance-events/{complianceEvent}',         [\App\Http\Controllers\Api\V1\ComplianceCalendarController::class, 'update'])->name('compliance-events.update');
        Route::delete('compliance-events/{complianceEvent}',      [\App\Http\Controllers\Api\V1\ComplianceCalendarController::class, 'destroy'])->name('compliance-events.destroy');
        Route::post('compliance-events/{complianceEvent}/complete', [\App\Http\Controllers\Api\V1\ComplianceCalendarController::class, 'complete'])->name('compliance-events.complete');

        // Reports
        Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
            Route::get('{type}',        [\App\Http\Controllers\Api\V1\ReportController::class, 'generate'])->name('generate');
            Route::get('{type}/export', [\App\Http\Controllers\Api\V1\ReportController::class, 'export'])->name('export')->middleware('permission:reports.export');
        });

        // Dashboard
        Route::get('dashboard/kpis',          [\App\Http\Controllers\Api\V1\DashboardController::class, 'kpis'])->name('dashboard.kpis');
        Route::get('dashboard/charts/{type}', [\App\Http\Controllers\Api\V1\DashboardController::class, 'chart'])->name('dashboard.charts');

        // Staff Management
        Route::apiResource('staff', \App\Http\Controllers\Api\V1\StaffController::class)->only(['index', 'show'])->middleware('permission:staff.view');
        Route::apiResource('staff', \App\Http\Controllers\Api\V1\StaffController::class)->only(['store'])->middleware('permission:staff.create');
        Route::apiResource('staff', \App\Http\Controllers\Api\V1\StaffController::class)->only(['update'])->middleware('permission:staff.edit');
        Route::apiResource('staff', \App\Http\Controllers\Api\V1\StaffController::class)->only(['destroy'])->middleware('permission:staff.delete');
        Route::post('staff/{staff}/reset-password',   [\App\Http\Controllers\Api\V1\StaffController::class, 'resetPassword'])->name('staff.reset-password')->middleware('permission:staff.reset_password');
        Route::put('staff/{staff}/toggle-status',     [\App\Http\Controllers\Api\V1\StaffController::class, 'toggleStatus'])->name('staff.toggle-status')->middleware('permission:staff.edit');
        Route::get('staff/{staff}/activity',          [\App\Http\Controllers\Api\V1\StaffController::class, 'activity'])->name('staff.activity')->middleware('permission:staff.view');

        // Settings
        Route::get('settings',          [\App\Http\Controllers\Api\V1\SettingController::class, 'index'])->name('settings.index')->middleware('permission:settings.view');
        Route::put('settings',          [\App\Http\Controllers\Api\V1\SettingController::class, 'update'])->name('settings.update')->middleware('permission:settings.edit');
        Route::post('settings/logo',    [\App\Http\Controllers\Api\V1\SettingController::class, 'uploadLogo'])->name('settings.logo')->middleware('permission:settings.edit');
        Route::get('settings/branding', [\App\Http\Controllers\Api\V1\SettingController::class, 'branding'])->name('settings.branding')->withoutMiddleware('auth:sanctum');
        Route::get('branding',         [\App\Http\Controllers\Api\V1\BrandingController::class, 'show'])->name('branding.show')->withoutMiddleware('auth:sanctum');
        Route::post('settings/test-email', [\App\Http\Controllers\Api\V1\SettingController::class, 'testEmail'])->name('settings.test-email')->middleware('permission:settings.edit');

        // Notifications
        Route::get('notifications',              [\App\Http\Controllers\Api\V1\NotificationController::class, 'index'])->name('notifications.index');
        Route::put('notifications/{id}/read',    [\App\Http\Controllers\Api\V1\NotificationController::class, 'markRead'])->name('notifications.read');
        Route::put('notifications/read-all',     [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllRead'])->name('notifications.read-all');

        // Audit Log
        Route::get('audit-log',        [\App\Http\Controllers\Api\V1\AuditLogController::class, 'index'])->name('audit-log.index');
        Route::get('audit-log/export', [\App\Http\Controllers\Api\V1\AuditLogController::class, 'export'])->name('audit-log.export');

        // Notification Preferences
        Route::get('notification-preferences', [\App\Http\Controllers\Api\V1\NotificationPreferenceController::class, 'index'])->name('notification-preferences.index');
        Route::put('notification-preferences', [\App\Http\Controllers\Api\V1\NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');

        // GL Ledger (Phase 28)
        Route::prefix('gl')->name('gl.')->group(function () {
            Route::get('accounts',      [\App\Http\Controllers\Api\V1\GlLedgerController::class, 'accounts'])->name('accounts');
            Route::post('accounts',     [\App\Http\Controllers\Api\V1\GlLedgerController::class, 'createAccount'])->name('accounts.create');
            Route::post('seed-accounts',[\App\Http\Controllers\Api\V1\GlLedgerController::class, 'seedAccounts'])->name('accounts.seed');
            Route::get('entries',       [\App\Http\Controllers\Api\V1\GlLedgerController::class, 'entries'])->name('entries');
            Route::post('entries',      [\App\Http\Controllers\Api\V1\GlLedgerController::class, 'createEntry'])->name('entries.create');
            Route::get('trial-balance', [\App\Http\Controllers\Api\V1\GlLedgerController::class, 'trialBalance'])->name('trial-balance');
        });

        // Notification Templates (Phase 25)
        Route::get('notification-templates', [\App\Http\Controllers\Api\V1\NotificationTemplateController::class, 'index'])->name('notification-templates.index');
        Route::put('notification-templates/{event}/{channel}', [\App\Http\Controllers\Api\V1\NotificationTemplateController::class, 'upsert'])->name('notification-templates.upsert');
        Route::delete('notification-templates/{event}/{channel}', [\App\Http\Controllers\Api\V1\NotificationTemplateController::class, 'destroy'])->name('notification-templates.destroy');
        Route::post('notification-templates/{event}/{channel}/preview', [\App\Http\Controllers\Api\V1\NotificationTemplateController::class, 'preview'])->name('notification-templates.preview');

        // Savings (Phase 30)
        Route::get('savings',                                [\App\Http\Controllers\Api\V1\SavingsController::class, 'index'])->name('savings.index');
        Route::post('savings',                               [\App\Http\Controllers\Api\V1\SavingsController::class, 'store'])->name('savings.store');
        Route::get('savings/{savings}',                      [\App\Http\Controllers\Api\V1\SavingsController::class, 'show'])->name('savings.show');
        Route::put('savings/{savings}/status',               [\App\Http\Controllers\Api\V1\SavingsController::class, 'updateStatus'])->name('savings.status');
        Route::post('savings/{savings}/deposit',             [\App\Http\Controllers\Api\V1\SavingsController::class, 'deposit'])->name('savings.deposit');
        Route::post('savings/{savings}/withdraw',            [\App\Http\Controllers\Api\V1\SavingsController::class, 'withdraw'])->name('savings.withdraw');
        Route::post('savings/{savings}/accrue-interest',     [\App\Http\Controllers\Api\V1\SavingsController::class, 'accrueInterest'])->name('savings.accrue-interest');
        Route::get('savings/{savings}/statement',            [\App\Http\Controllers\Api\V1\SavingsController::class, 'statement'])->name('savings.statement');
        Route::get('savings/{savings}/goal-progress',        [\App\Http\Controllers\Api\V1\SavingsController::class, 'goalProgress'])->name('savings.goal-progress');
        Route::post('savings/{savings}/mature',              [\App\Http\Controllers\Api\V1\SavingsController::class, 'matureFd'])->name('savings.mature');
        Route::get('savings-matured',                        [\App\Http\Controllers\Api\V1\SavingsController::class, 'matured'])->name('savings.matured');

        // Loan Groups (Phase 31)
        Route::get('loan-groups',                                      [\App\Http\Controllers\Api\V1\LoanGroupController::class, 'index'])->name('loan-groups.index');
        Route::post('loan-groups',                                     [\App\Http\Controllers\Api\V1\LoanGroupController::class, 'store'])->name('loan-groups.store');
        Route::get('loan-groups/{loanGroup}',                          [\App\Http\Controllers\Api\V1\LoanGroupController::class, 'show'])->name('loan-groups.show');
        Route::put('loan-groups/{loanGroup}',                          [\App\Http\Controllers\Api\V1\LoanGroupController::class, 'update'])->name('loan-groups.update');
        Route::delete('loan-groups/{loanGroup}',                       [\App\Http\Controllers\Api\V1\LoanGroupController::class, 'destroy'])->name('loan-groups.destroy');
        Route::post('loan-groups/{loanGroup}/members',                 [\App\Http\Controllers\Api\V1\LoanGroupController::class, 'addMember'])->name('loan-groups.members.add');
        Route::delete('loan-groups/{loanGroup}/members/{borrower}',    [\App\Http\Controllers\Api\V1\LoanGroupController::class, 'removeMember'])->name('loan-groups.members.remove');

        // Staff Targets (Phase 32)
        Route::get('staff-targets',             [\App\Http\Controllers\Api\V1\StaffTargetController::class, 'index'])->name('staff-targets.index');
        Route::post('staff-targets',            [\App\Http\Controllers\Api\V1\StaffTargetController::class, 'upsert'])->name('staff-targets.upsert');
        Route::delete('staff-targets/{staffTarget}', [\App\Http\Controllers\Api\V1\StaffTargetController::class, 'destroy'])->name('staff-targets.destroy');
        Route::get('staff-targets/performance', [\App\Http\Controllers\Api\V1\StaffTargetController::class, 'performance'])->name('staff-targets.performance');

        // Outbound Webhook Endpoints (Phase 33)
        Route::prefix('webhook-endpoints')->name('webhook-endpoints.')->group(function () {
            Route::get('/',                                          [\App\Http\Controllers\Api\V1\WebhookEndpointController::class, 'index'])->name('index');
            Route::post('/',                                         [\App\Http\Controllers\Api\V1\WebhookEndpointController::class, 'store'])->name('store');
            Route::get('/{webhookEndpoint}',                         [\App\Http\Controllers\Api\V1\WebhookEndpointController::class, 'show'])->name('show');
            Route::put('/{webhookEndpoint}',                         [\App\Http\Controllers\Api\V1\WebhookEndpointController::class, 'update'])->name('update');
            Route::delete('/{webhookEndpoint}',                      [\App\Http\Controllers\Api\V1\WebhookEndpointController::class, 'destroy'])->name('destroy');
            Route::post('/{webhookEndpoint}/rotate-secret',          [\App\Http\Controllers\Api\V1\WebhookEndpointController::class, 'rotateSecret'])->name('rotate-secret');
            Route::get('/{webhookEndpoint}/deliveries',              [\App\Http\Controllers\Api\V1\WebhookEndpointController::class, 'deliveries'])->name('deliveries');
            Route::post('/{webhookEndpoint}/deliveries/{webhookDelivery}/retry', [\App\Http\Controllers\Api\V1\WebhookEndpointController::class, 'retry'])->name('deliveries.retry');
        });

        // Branding (Phase 34)
        Route::put('branding',         [\App\Http\Controllers\Api\V1\BrandingController::class, 'update'])->name('branding.update');
        Route::post('branding/logo',   [\App\Http\Controllers\Api\V1\BrandingController::class, 'uploadLogo'])->name('branding.logo');
        Route::post('branding/favicon',[\App\Http\Controllers\Api\V1\BrandingController::class, 'uploadFavicon'])->name('branding.favicon');

        // Branches (P9)
        Route::apiResource('branches', \App\Http\Controllers\Api\V1\BranchController::class);

        // CRM — Leads & Interactions (Phase 36)
        Route::get('leads/pipeline',                         [\App\Http\Controllers\Api\V1\LeadController::class, 'pipeline'])->name('leads.pipeline');
        Route::get('leads',                                  [\App\Http\Controllers\Api\V1\LeadController::class, 'index'])->name('leads.index');
        Route::post('leads',                                 [\App\Http\Controllers\Api\V1\LeadController::class, 'store'])->name('leads.store');
        Route::get('leads/{lead}',                           [\App\Http\Controllers\Api\V1\LeadController::class, 'show'])->name('leads.show');
        Route::put('leads/{lead}',                           [\App\Http\Controllers\Api\V1\LeadController::class, 'update'])->name('leads.update');
        Route::delete('leads/{lead}',                        [\App\Http\Controllers\Api\V1\LeadController::class, 'destroy'])->name('leads.destroy');
        Route::post('leads/{lead}/convert',                  [\App\Http\Controllers\Api\V1\LeadController::class, 'convert'])->name('leads.convert');
        Route::post('leads/{lead}/interactions',             [\App\Http\Controllers\Api\V1\LeadController::class, 'addInteraction'])->name('leads.interactions.store');

        // Risk Management (Phase 37)
        Route::get('risk-policies',                          [\App\Http\Controllers\Api\V1\RiskPolicyController::class, 'index'])->name('risk-policies.index');
        Route::post('risk-policies',                         [\App\Http\Controllers\Api\V1\RiskPolicyController::class, 'store'])->name('risk-policies.store');
        Route::get('risk-policies/{riskPolicy}',             [\App\Http\Controllers\Api\V1\RiskPolicyController::class, 'show'])->name('risk-policies.show');
        Route::put('risk-policies/{riskPolicy}',             [\App\Http\Controllers\Api\V1\RiskPolicyController::class, 'update'])->name('risk-policies.update');
        Route::delete('risk-policies/{riskPolicy}',          [\App\Http\Controllers\Api\V1\RiskPolicyController::class, 'destroy'])->name('risk-policies.destroy');
        Route::get('risk-policy/rule-types',                 [\App\Http\Controllers\Api\V1\RiskPolicyController::class, 'ruleTypes'])->name('risk-policies.rule-types');
        Route::post('loans/{loan}/risk-assess',              [\App\Http\Controllers\Api\V1\RiskPolicyController::class, 'assess'])->name('loans.risk-assess');
        Route::get('loans/{loan}/risk-flags',                [\App\Http\Controllers\Api\V1\RiskPolicyController::class, 'flags'])->name('loans.risk-flags');
        Route::post('risk-flags/{riskFlag}/override',        [\App\Http\Controllers\Api\V1\RiskPolicyController::class, 'override'])->name('risk-flags.override');

        // Agents / DSA Network (Phase 39)
        Route::get('agents/commissions',                          [\App\Http\Controllers\Api\V1\AgentController::class, 'allCommissions'])->name('agents.commissions.all');
        Route::get('agents',                                      [\App\Http\Controllers\Api\V1\AgentController::class, 'index'])->name('agents.index');
        Route::post('agents',                                     [\App\Http\Controllers\Api\V1\AgentController::class, 'store'])->name('agents.store');
        Route::get('agents/{agent}',                              [\App\Http\Controllers\Api\V1\AgentController::class, 'show'])->name('agents.show');
        Route::put('agents/{agent}',                              [\App\Http\Controllers\Api\V1\AgentController::class, 'update'])->name('agents.update');
        Route::delete('agents/{agent}',                           [\App\Http\Controllers\Api\V1\AgentController::class, 'destroy'])->name('agents.destroy');
        Route::get('agents/{agent}/commissions',                  [\App\Http\Controllers\Api\V1\AgentController::class, 'commissions'])->name('agents.commissions');
        Route::get('agents/{agent}/loans',                        [\App\Http\Controllers\Api\V1\AgentController::class, 'loans'])->name('agents.loans');
        Route::post('agent-commissions/{agentCommission}/approve',[\App\Http\Controllers\Api\V1\AgentController::class, 'approveCommission'])->name('agent-commissions.approve');
        Route::post('agent-commissions/{agentCommission}/pay',    [\App\Http\Controllers\Api\V1\AgentController::class, 'payCommission'])->name('agent-commissions.pay');

        // Campaigns (Phase 59)
        Route::apiResource('campaigns', \App\Http\Controllers\Api\V1\CampaignController::class);
        Route::post('campaigns/{campaign}/dispatch',                    [\App\Http\Controllers\Api\V1\CampaignController::class, 'dispatch'])->name('campaigns.dispatch');
        Route::get('campaigns/{campaign}/stats',                        [\App\Http\Controllers\Api\V1\CampaignController::class, 'stats'])->name('campaigns.stats');
        Route::post('campaigns/{campaign}/recipients/{recipient}/open', [\App\Http\Controllers\Api\V1\CampaignController::class, 'trackOpen'])->name('campaigns.open');

        // API Client Management (Phase 60)
        Route::apiResource('api-clients', \App\Http\Controllers\Api\V1\ApiClientController::class);
        Route::post('api-clients/{apiClient}/rotate-key', [\App\Http\Controllers\Api\V1\ApiClientController::class, 'rotateKey'])->name('api-clients.rotate-key');
        Route::get('api-clients/{apiClient}/logs',         [\App\Http\Controllers\Api\V1\ApiClientController::class, 'logs'])->name('api-clients.logs');

        // Collections Automation (Phase 56)
        Route::get('escalation-rules',                          [\App\Http\Controllers\Api\V1\CollectionCaseController::class, 'rules'])->name('escalation-rules.index');
        Route::post('escalation-rules',                         [\App\Http\Controllers\Api\V1\CollectionCaseController::class, 'storeRule'])->name('escalation-rules.store');
        Route::put('escalation-rules/{rule}',                   [\App\Http\Controllers\Api\V1\CollectionCaseController::class, 'updateRule'])->name('escalation-rules.update');
        Route::delete('escalation-rules/{rule}',                [\App\Http\Controllers\Api\V1\CollectionCaseController::class, 'destroyRule'])->name('escalation-rules.destroy');
        Route::get('collection-cases',                          [\App\Http\Controllers\Api\V1\CollectionCaseController::class, 'index'])->name('collection-cases.index');
        Route::get('collection-cases/{collectionCase}',         [\App\Http\Controllers\Api\V1\CollectionCaseController::class, 'show'])->name('collection-cases.show');
        Route::put('collection-cases/{collectionCase}',         [\App\Http\Controllers\Api\V1\CollectionCaseController::class, 'update'])->name('collection-cases.update');
        Route::post('loans/{loan}/escalate',                    [\App\Http\Controllers\Api\V1\CollectionCaseController::class, 'escalateLoan'])->name('loans.escalate');
        Route::get('collection-cases/{collectionCase}/promises',[\App\Http\Controllers\Api\V1\CollectionCaseController::class, 'promises'])->name('collection-cases.promises');
        Route::post('collection-cases/{collectionCase}/promises',[\App\Http\Controllers\Api\V1\CollectionCaseController::class, 'storePromise'])->name('collection-cases.promises.store');

        // Reconciliation (Phase 57)
        Route::get('reconciliation',                                                    [\App\Http\Controllers\Api\V1\ReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::post('reconciliation/import',                                            [\App\Http\Controllers\Api\V1\ReconciliationController::class, 'import'])->name('reconciliation.import');
        Route::get('reconciliation/{statement}',                                        [\App\Http\Controllers\Api\V1\ReconciliationController::class, 'show'])->name('reconciliation.show');
        Route::post('reconciliation/{statement}/reconcile',                             [\App\Http\Controllers\Api\V1\ReconciliationController::class, 'reconcile'])->name('reconciliation.reconcile');
        Route::get('reconciliation/{statement}/unmatched',                              [\App\Http\Controllers\Api\V1\ReconciliationController::class, 'unmatched'])->name('reconciliation.unmatched');
        Route::post('reconciliation/transactions/{transaction}/match',                  [\App\Http\Controllers\Api\V1\ReconciliationController::class, 'match'])->name('reconciliation.match');
        Route::post('reconciliation/transactions/{transaction}/ignore',                 [\App\Http\Controllers\Api\V1\ReconciliationController::class, 'ignore'])->name('reconciliation.ignore');

        // ─── Branch Performance Dashboards ───────────────────────────
        Route::get('branches/{branch}/performance/pnl',       [\App\Http\Controllers\Api\V1\BranchPerformanceController::class, 'pnl'])->name('branches.performance.pnl');
        Route::get('branches/{branch}/performance/portfolio', [\App\Http\Controllers\Api\V1\BranchPerformanceController::class, 'portfolio'])->name('branches.performance.portfolio');
        Route::get('branches/{branch}/performance/officers',  [\App\Http\Controllers\Api\V1\BranchPerformanceController::class, 'officers'])->name('branches.performance.officers');

        // ─── Loan Calculator ──────────────────────────────────────────
        Route::post('calculator/calculate',  [\App\Http\Controllers\Api\V1\LoanCalculatorController::class, 'calculate'])->name('calculator.calculate');

        // ─── Onboarding Wizard ────────────────────────────────────────
        Route::get('onboarding/progress',              [\App\Http\Controllers\Api\V1\OnboardingWizardController::class, 'progress'])->name('onboarding.progress');
        Route::post('onboarding/steps/{key}/complete', [\App\Http\Controllers\Api\V1\OnboardingWizardController::class, 'complete'])->name('onboarding.steps.complete');
        Route::post('onboarding/steps/{key}/reset',    [\App\Http\Controllers\Api\V1\OnboardingWizardController::class, 'reset'])->name('onboarding.steps.reset');

        // ─── Staff Commissions ────────────────────────────────────────
        Route::get('commission-rules',                    [\App\Http\Controllers\Api\V1\CommissionController::class, 'rules'])->name('commission-rules.index');
        Route::post('commission-rules',                   [\App\Http\Controllers\Api\V1\CommissionController::class, 'storeRule'])->name('commission-rules.store');
        Route::put('commission-rules/{rule}',             [\App\Http\Controllers\Api\V1\CommissionController::class, 'updateRule'])->name('commission-rules.update');
        Route::delete('commission-rules/{rule}',          [\App\Http\Controllers\Api\V1\CommissionController::class, 'destroyRule'])->name('commission-rules.destroy');
        Route::get('commissions',                         [\App\Http\Controllers\Api\V1\CommissionController::class, 'index'])->name('commissions.index');
        Route::get('commissions/users/{userId}/summary',  [\App\Http\Controllers\Api\V1\CommissionController::class, 'summary'])->name('commissions.summary');
        Route::post('commissions/approve-period',         [\App\Http\Controllers\Api\V1\CommissionController::class, 'approvePeriod'])->name('commissions.approve-period');
        Route::post('commissions/mark-paid',              [\App\Http\Controllers\Api\V1\CommissionController::class, 'markPaid'])->name('commissions.mark-paid');

        // ─── Loan Product Marketplace (tenant-side management) ────────
        Route::get('marketplace/my-products',         [\App\Http\Controllers\Api\V1\PublicMarketplaceController::class, 'myProducts'])->name('marketplace.my-products');
        Route::post('marketplace/products',           [\App\Http\Controllers\Api\V1\PublicMarketplaceController::class, 'publish'])->name('marketplace.publish');
        Route::delete('marketplace/products/{id}',    [\App\Http\Controllers\Api\V1\PublicMarketplaceController::class, 'unpublish'])->name('marketplace.unpublish');
        Route::get('marketplace/products',            [\App\Http\Controllers\Api\V1\PublicMarketplaceController::class, 'browse'])->name('marketplace.browse');
        Route::get('marketplace/products/{id}',       [\App\Http\Controllers\Api\V1\PublicMarketplaceController::class, 'show'])->name('marketplace.show');

        // ─── CRB (Credit Reference Bureau) ───────────────────────────
        Route::post('crb/check',                                    [\App\Http\Controllers\Api\V1\CrbController::class, 'check'])->name('crb.check');
        Route::get('crb/inquiries',                                 [\App\Http\Controllers\Api\V1\CrbController::class, 'inquiries'])->name('crb.inquiries');
        Route::get('crb/report/{hash}',                             [\App\Http\Controllers\Api\V1\CrbController::class, 'report'])->name('crb.report');
        Route::get('borrowers/{borrower}/crb',                      [\App\Http\Controllers\Api\V1\CrbController::class, 'borrowerReport'])->name('crb.borrower-report');
        Route::post('borrowers/{borrower}/crb/recalculate',         [\App\Http\Controllers\Api\V1\CrbController::class, 'recalculate'])->name('crb.recalculate');

        // ─── KYC Pull (Ghost User identity lookup) ────────────────────
        Route::post('borrowers/kyc-lookup',                         [\App\Http\Controllers\Api\V1\BorrowerController::class, 'kycLookup'])->name('borrowers.kyc-lookup');
        Route::post('borrowers/{borrower}/kyc-import',              [\App\Http\Controllers\Api\V1\BorrowerController::class, 'kycImport'])->name('borrowers.kyc-import');

        // ─── Repo Marketplace — Tenant Management ─────────────────────
        Route::apiResource('repo-items', \App\Http\Controllers\Api\V1\RepoItemController::class);
        Route::post('repo-items/{id}/mark-sold',                    [\App\Http\Controllers\Api\V1\RepoItemController::class, 'markSold'])->name('repo-items.mark-sold');
        Route::get('repo-items/{id}/enquiries',                     [\App\Http\Controllers\Api\V1\RepoItemController::class, 'enquiries'])->name('repo-items.enquiries');
        Route::post('repo-items/{id}/enquiries/{enquiryId}/reply',  [\App\Http\Controllers\Api\V1\RepoItemController::class, 'reply'])->name('repo-items.enquiries.reply');

        // Featured Repo Items (tenant-paid promotions)
        Route::get('featured-items/quote',                [\App\Http\Controllers\Api\V1\FeaturedItemController::class, 'quote'])->name('featured-items.quote');
        Route::get('featured-items',                      [\App\Http\Controllers\Api\V1\FeaturedItemController::class, 'index'])->name('featured-items.index');
        Route::post('featured-items',                     [\App\Http\Controllers\Api\V1\FeaturedItemController::class, 'store'])->name('featured-items.store');
        Route::post('featured-items/{id}/confirm',        [\App\Http\Controllers\Api\V1\FeaturedItemController::class, 'confirmPayment'])->name('featured-items.confirm');
        Route::delete('featured-items/{id}',              [\App\Http\Controllers\Api\V1\FeaturedItemController::class, 'destroy'])->name('featured-items.destroy');

        // Hot Deals
        Route::get('hot-deals',                           [\App\Http\Controllers\Api\V1\HotDealController::class, 'index'])->name('hot-deals.index');
        Route::post('hot-deals',                          [\App\Http\Controllers\Api\V1\HotDealController::class, 'store'])->name('hot-deals.store');
        Route::put('hot-deals/{id}',                      [\App\Http\Controllers\Api\V1\HotDealController::class, 'update'])->name('hot-deals.update');
        Route::delete('hot-deals/{id}',                   [\App\Http\Controllers\Api\V1\HotDealController::class, 'destroy'])->name('hot-deals.destroy');
        Route::get('hot-deals/{id}/leads',                [\App\Http\Controllers\Api\V1\HotDealController::class, 'leads'])->name('hot-deals.leads');
        Route::post('hot-deals/{id}/toggle',              [\App\Http\Controllers\Api\V1\HotDealController::class, 'toggle'])->name('hot-deals.toggle');

        // ─── Tax & Regulatory Compliance (Phase 56) ───────────────────
        Route::prefix('tax')->name('tax.')->group(function () {
            Route::get('configurations',                      [\App\Http\Controllers\Api\V1\TaxComplianceController::class, 'configurations'])->name('configurations');
            Route::post('configurations',                     [\App\Http\Controllers\Api\V1\TaxComplianceController::class, 'storeConfig'])->name('configurations.store');
            Route::put('configurations/{config}',             [\App\Http\Controllers\Api\V1\TaxComplianceController::class, 'updateConfig'])->name('configurations.update');
            Route::get('wht-summary',                         [\App\Http\Controllers\Api\V1\TaxComplianceController::class, 'whtSummary'])->name('wht-summary');
            Route::post('wht-summary/{period}/remit',         [\App\Http\Controllers\Api\V1\TaxComplianceController::class, 'markRemitted'])->name('wht-summary.remit');
            Route::get('par-report',                          [\App\Http\Controllers\Api\V1\TaxComplianceController::class, 'parReport'])->name('par-report');
            Route::get('capital-adequacy',                    [\App\Http\Controllers\Api\V1\TaxComplianceController::class, 'capitalAdequacy'])->name('capital-adequacy');
            Route::get('computations',                        [\App\Http\Controllers\Api\V1\TaxComplianceController::class, 'computations'])->name('computations');
        });

        // ─── Loan Offer Engine (Phase 57) ─────────────────────────────
        Route::prefix('loan-offers')->name('loan-offers.')->group(function () {
            Route::get('rules',                   [\App\Http\Controllers\Api\V1\LoanOfferController::class, 'rules'])->name('rules');
            Route::post('rules',                  [\App\Http\Controllers\Api\V1\LoanOfferController::class, 'storeRule'])->name('rules.store');
            Route::put('rules/{rule}',            [\App\Http\Controllers\Api\V1\LoanOfferController::class, 'updateRule'])->name('rules.update');
            Route::delete('rules/{rule}',         [\App\Http\Controllers\Api\V1\LoanOfferController::class, 'destroyRule'])->name('rules.destroy');
            Route::get('/',                       [\App\Http\Controllers\Api\V1\LoanOfferController::class, 'index'])->name('index');
            Route::post('generate',               [\App\Http\Controllers\Api\V1\LoanOfferController::class, 'generate'])->name('generate');
            Route::get('{offer}',                 [\App\Http\Controllers\Api\V1\LoanOfferController::class, 'show'])->name('show');
            Route::post('{offer}/accept',         [\App\Http\Controllers\Api\V1\LoanOfferController::class, 'accept'])->name('accept');
            Route::post('{offer}/decline',        [\App\Http\Controllers\Api\V1\LoanOfferController::class, 'decline'])->name('decline');
            Route::post('{offer}/expire',         [\App\Http\Controllers\Api\V1\LoanOfferController::class, 'expire'])->name('expire');
        });
    });

    // ─── Open Banking (Phase 60 — API-key auth) ──────────────────
    Route::prefix('open/v1')->name('open.')->middleware('api-gateway')->group(function () {
        Route::get('products',                    [\App\Http\Controllers\Api\V1\OpenBankingController::class, 'products'])->name('products');
        Route::post('loan/apply',                 [\App\Http\Controllers\Api\V1\OpenBankingController::class, 'applyLoan'])->name('loan.apply');
        Route::get('loan/{reference}/status',     [\App\Http\Controllers\Api\V1\OpenBankingController::class, 'loanStatus'])->name('loan.status');
        Route::post('payment/initiate',           [\App\Http\Controllers\Api\V1\OpenBankingController::class, 'initiatePayment'])->name('payment.initiate');
    });

    // ─── Borrower Protected Routes (Sanctum — borrower tokens) ───
    Route::prefix('me')->name('borrower.')->middleware('auth:sanctum')->group(function () {
        Route::get('/',        [\App\Http\Controllers\Api\V1\Borrower\ProfileController::class, 'show'])->name('profile');
        Route::get('loans',    [\App\Http\Controllers\Api\V1\Borrower\ProfileController::class, 'loans'])->name('loans');
        Route::get('payments', [\App\Http\Controllers\Api\V1\Borrower\ProfileController::class, 'payments'])->name('payments');

        Route::put('profile',     [\App\Http\Controllers\Api\V1\Borrower\ProfileController::class, 'update'])->name('profile.update');
        Route::post('kyc/upload', [\App\Http\Controllers\Api\V1\KycController::class, 'borrowerUpload'])->name('kyc.upload');
        Route::get('credit-score', [\App\Http\Controllers\Api\V1\Borrower\ProfileController::class, 'creditScore'])->name('credit-score');
        Route::get('payments/{payment}/receipt', [\App\Http\Controllers\Api\V1\Borrower\ProfileController::class, 'receipt'])->name('payments.receipt');

        // Borrower-initiated payment
        Route::post('payments/initiate', [\App\Http\Controllers\Api\V1\Borrower\ProfileController::class, 'initiatePayment'])->name('payments.initiate');

        // Loan application self-service (P16)
        Route::get('loan-products',                   [\App\Http\Controllers\Api\V1\Borrower\LoanController::class, 'products'])->name('loan-products');
        Route::post('loans/apply',                    [\App\Http\Controllers\Api\V1\Borrower\LoanController::class, 'apply'])->name('loans.apply');
        Route::get('loans/{id}',                      [\App\Http\Controllers\Api\V1\Borrower\LoanController::class, 'show'])->name('loans.show');
        Route::get('payment-gateways',                           [\App\Http\Controllers\Api\V1\Borrower\LoanController::class, 'paymentGateways'])->name('payment-gateways');
        Route::post('loans/{id}/initiate-payment',               [\App\Http\Controllers\Api\V1\Borrower\LoanController::class, 'initiatePayment'])->name('loans.initiate-payment');
        Route::get('loans/{id}/payment-status/{reference}',      [\App\Http\Controllers\Api\V1\Borrower\LoanController::class, 'paymentStatus'])->name('loans.payment-status');
        Route::get('statement/pdf',                              [\App\Http\Controllers\Api\V1\Borrower\LoanController::class, 'statementPdf'])->name('statement.pdf');

        // Borrower notifications
        Route::get('notifications',             [\App\Http\Controllers\Api\V1\Borrower\NotificationController::class, 'index'])->name('notifications.index');
        Route::put('notifications/read-all',    [\App\Http\Controllers\Api\V1\Borrower\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
        Route::put('notifications/{id}/read',   [\App\Http\Controllers\Api\V1\Borrower\NotificationController::class, 'markRead'])->name('notifications.read');

        // Device Tokens (Push Notifications)
        Route::post('device-tokens',    [\App\Http\Controllers\Api\V1\Borrower\DeviceTokenController::class, 'register'])->name('device-tokens.register');
        Route::delete('device-tokens',  [\App\Http\Controllers\Api\V1\Borrower\DeviceTokenController::class, 'unregister'])->name('device-tokens.unregister');

        // Public Loan Product Marketplace (cross-tenant browsing)
        Route::get('public-products',             [\App\Http\Controllers\Api\V1\Borrower\PublicProductController::class, 'browse'])->name('public-products.browse');
        Route::get('public-products/{id}',        [\App\Http\Controllers\Api\V1\Borrower\PublicProductController::class, 'show'])->name('public-products.show');
        Route::post('public-products/{id}/apply', [\App\Http\Controllers\Api\V1\Borrower\PublicProductController::class, 'apply'])->name('public-products.apply');

        // Marketplace (P11 — gated by feature flag)
        Route::prefix('marketplace')->name('marketplace.')->group(function () {
            Route::get('listings',        [\App\Http\Controllers\Api\V1\Borrower\MarketplaceController::class, 'myListings'])->name('listings');
            Route::post('listings',       [\App\Http\Controllers\Api\V1\Borrower\MarketplaceController::class, 'createListing'])->name('listings.create');
            Route::put('listings/{id}/withdraw', [\App\Http\Controllers\Api\V1\Borrower\MarketplaceController::class, 'withdraw'])->name('listings.withdraw');
            Route::post('interests/{id}/accept', [\App\Http\Controllers\Api\V1\Borrower\MarketplaceController::class, 'acceptInterest'])->name('interests.accept');
        });
    });

    // ─── Repo Marketplace — Public (Ghost Users) ──────────────────
    Route::prefix('public')->name('public.')->group(function () {

        // Ghost auth (no middleware)
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('register',    [\App\Http\Controllers\Api\V1\Public\GhostAuthController::class, 'register'])->name('register');
            Route::post('request-otp', [\App\Http\Controllers\Api\V1\Public\GhostAuthController::class, 'requestOtp'])->name('request-otp');
            Route::post('verify-otp',  [\App\Http\Controllers\Api\V1\Public\GhostAuthController::class, 'verifyOtp'])->name('verify-otp');

            Route::middleware('auth:sanctum')->group(function () {
                Route::get('profile',  [\App\Http\Controllers\Api\V1\Public\GhostAuthController::class, 'profile'])->name('profile');
                Route::put('profile',  [\App\Http\Controllers\Api\V1\Public\GhostAuthController::class, 'updateProfile'])->name('profile.update');
            });
        });

        // Item browsing (no auth)
        Route::get('items',            [\App\Http\Controllers\Api\V1\Public\RepoItemController::class, 'browse'])->name('items.browse');
        Route::get('items/{id}',       [\App\Http\Controllers\Api\V1\Public\RepoItemController::class, 'show'])->name('items.show');

        // Featured items & Hot Deals (no auth)
        Route::get('featured-items',               [\App\Http\Controllers\Api\V1\Public\FeaturedController::class, 'featuredItems'])->name('featured-items');
        Route::get('hot-deals',                    [\App\Http\Controllers\Api\V1\Public\FeaturedController::class, 'hotDeals'])->name('hot-deals.index');
        Route::get('hot-deals/{id}',               [\App\Http\Controllers\Api\V1\Public\FeaturedController::class, 'showDeal'])->name('hot-deals.show');
        Route::post('hot-deals/{id}/enquire',      [\App\Http\Controllers\Api\V1\Public\FeaturedController::class, 'enquireDeal'])->name('hot-deals.enquire');

        // Ghost-auth required
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('items/{id}/enquire', [\App\Http\Controllers\Api\V1\Public\RepoItemController::class, 'enquire'])->name('items.enquire');
            Route::get('my-enquiries',        [\App\Http\Controllers\Api\V1\Public\RepoItemController::class, 'myEnquiries'])->name('my-enquiries');

            // Cart
            Route::get('cart',            [\App\Http\Controllers\Api\V1\Public\CartController::class, 'index'])->name('cart.index');
            Route::post('cart',           [\App\Http\Controllers\Api\V1\Public\CartController::class, 'add'])->name('cart.add');
            Route::delete('cart/{cartId}',[\App\Http\Controllers\Api\V1\Public\CartController::class, 'remove'])->name('cart.remove');
        });
    });

    // ─── Mobile Money Webhooks (HMAC signature verified, no auth) ───
    Route::prefix('webhooks')->name('webhooks.')->group(function () {
        Route::post('flutterwave', [\App\Http\Controllers\Webhook\FlutterwaveWebhookController::class, 'handle'])->name('flutterwave');
        Route::post('pawapay',     [\App\Http\Controllers\Webhook\PawaPayWebhookController::class, 'handle'])->name('pawapay');
        Route::post('lipila',      [\App\Http\Controllers\Webhook\LipilaWebhookController::class, 'handle'])->name('lipila');

        // Subscription/billing webhooks (central DB — no tenant context)
        Route::post('subscription/{gateway}', [\App\Http\Controllers\Webhook\SubscriptionWebhookController::class, 'handle'])->name('subscription');
    });

    // ─── Marketplace (Lender-facing — P11) ──────────────────────
    // ─── E-Signatures & Digital Contracts (Phase 56) ──────────────────────────
    Route::middleware('auth:sanctum')->prefix('loans/{loan}')->name('loans.')->group(function () {
        Route::get('agreement',              [\App\Http\Controllers\Api\V1\ESignatureController::class, 'show'])->name('agreement.show');
        Route::post('agreement/generate',    [\App\Http\Controllers\Api\V1\ESignatureController::class, 'generate'])->name('agreement.generate');
        Route::post('agreement/send-otp',    [\App\Http\Controllers\Api\V1\ESignatureController::class, 'sendOtp'])->name('agreement.send-otp');
        Route::post('agreement/sign',        [\App\Http\Controllers\Api\V1\ESignatureController::class, 'sign'])->name('agreement.sign');
        Route::get('agreement/audit',        [\App\Http\Controllers\Api\V1\ESignatureController::class, 'audit'])->name('agreement.audit');
        Route::get('agreement/download',     [\App\Http\Controllers\Api\V1\ESignatureController::class, 'download'])->name('agreement.download');
    });

    // ─── Regulatory Reporting Engine (Phase 59) ──────────────────────────────
    Route::middleware('auth:sanctum')->prefix('regulatory')->name('regulatory.')->group(function () {
        Route::post('generate',               [\App\Http\Controllers\Api\V1\RegulatoryReportController::class, 'generate'])->name('generate');
        Route::get('reports',                 [\App\Http\Controllers\Api\V1\RegulatoryReportController::class, 'index'])->name('reports');
        Route::get('reports/{report}',        [\App\Http\Controllers\Api\V1\RegulatoryReportController::class, 'show'])->name('reports.show');
        Route::post('reports/{report}/email', [\App\Http\Controllers\Api\V1\RegulatoryReportController::class, 'email'])->name('reports.email');
        Route::get('configs',                 [\App\Http\Controllers\Api\V1\RegulatoryReportController::class, 'configs'])->name('configs');
        Route::post('configs',                [\App\Http\Controllers\Api\V1\RegulatoryReportController::class, 'upsertConfig'])->name('configs.upsert');
    });

    // ─── Customer Loyalty & Rewards (Phase 58) ───────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('borrowers/{borrower}/loyalty',        [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'show'])->name('loyalty.show');
        Route::post('borrowers/{borrower}/loyalty/redeem',[\App\Http\Controllers\Api\V1\LoyaltyController::class, 'redeem'])->name('loyalty.redeem');
        Route::get('loyalty/tiers',                       [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'tiers'])->name('loyalty.tiers');
        Route::post('loyalty/tiers',                      [\App\Http\Controllers\Api\V1\LoyaltyController::class, 'upsertTier'])->name('loyalty.tiers.upsert');
    });

    // ─── Field Collection App API (Phase 57) ─────────────────────────────────
    Route::middleware('auth:sanctum')->prefix('field')->name('field.')->group(function () {
        Route::post('check-in',      [\App\Http\Controllers\Api\V1\FieldCollectionController::class, 'checkIn'])->name('check-in');
        Route::get('check-ins',      [\App\Http\Controllers\Api\V1\FieldCollectionController::class, 'checkIns'])->name('check-ins');
        Route::post('collect',       [\App\Http\Controllers\Api\V1\FieldCollectionController::class, 'collect'])->name('collect');
        Route::get('collections',    [\App\Http\Controllers\Api\V1\FieldCollectionController::class, 'collections'])->name('collections');
        Route::get('loans',          [\App\Http\Controllers\Api\V1\FieldCollectionController::class, 'loans'])->name('loans');
        Route::post('sync',          [\App\Http\Controllers\Api\V1\FieldCollectionController::class, 'syncSubmit'])->name('sync');
        Route::get('sync/pending',   [\App\Http\Controllers\Api\V1\FieldCollectionController::class, 'syncPending'])->name('sync.pending');
    });

    Route::prefix('marketplace')->name('marketplace.')->middleware('auth:sanctum')->group(function () {
        Route::get('listings',           [\App\Http\Controllers\Api\V1\MarketplaceController::class, 'index'])->name('listings');
        Route::get('listings/{id}',      [\App\Http\Controllers\Api\V1\MarketplaceController::class, 'show'])->name('listings.show');
        Route::post('listings/{id}/express-interest', [\App\Http\Controllers\Api\V1\MarketplaceController::class, 'expressInterest'])->name('listings.interest');
        Route::get('my-interests',       [\App\Http\Controllers\Api\V1\MarketplaceController::class, 'myInterests'])->name('interests');
        Route::get('reviews/{globalId}', [\App\Http\Controllers\Api\V1\MarketplaceController::class, 'reviews'])->name('reviews');
        Route::post('reviews',           [\App\Http\Controllers\Api\V1\MarketplaceController::class, 'postReview'])->name('reviews.create');
    });
});

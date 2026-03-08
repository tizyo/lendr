<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — LENDR
|--------------------------------------------------------------------------
| All routes prefixed with /api/v1
| Staff auth: Laravel Sanctum (Bearer token)
| Borrower auth: custom guard (OTP/PIN based)
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ─── Staff Auth ───────────────────────────────────
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'login'])->name('login');
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'logout'])->name('logout');
            Route::post('refresh', [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'refresh'])->name('refresh');
            Route::post('2fa/verify', [\App\Http\Controllers\Api\V1\Auth\StaffAuthController::class, 'verify2fa'])->name('2fa.verify');
        });
    });

    // ─── Borrower Auth ────────────────────────────────
    Route::prefix('borrower/auth')->name('borrower.auth.')->group(function () {
        Route::post('request-otp', [\App\Http\Controllers\Api\V1\Auth\BorrowerAuthController::class, 'requestOtp'])->name('request-otp');
        Route::post('verify-otp', [\App\Http\Controllers\Api\V1\Auth\BorrowerAuthController::class, 'verifyOtp'])->name('verify-otp');
        Route::post('set-pin', [\App\Http\Controllers\Api\V1\Auth\BorrowerAuthController::class, 'setPin'])->name('set-pin');
    });

    // ─── Protected Staff Routes ────────────────────────
    Route::middleware('auth:sanctum')->group(function () {

        // Borrowers
        Route::apiResource('borrowers', \App\Http\Controllers\Api\V1\BorrowerController::class);
        Route::get('borrowers/{borrower}/loans', [\App\Http\Controllers\Api\V1\BorrowerController::class, 'loans'])->name('borrowers.loans');
        Route::get('borrowers/{borrower}/statement', [\App\Http\Controllers\Api\V1\BorrowerController::class, 'statement'])->name('borrowers.statement');

        // KYC
        Route::post('borrowers/{borrower}/kyc/documents', [\App\Http\Controllers\Api\V1\KycController::class, 'upload'])->name('kyc.upload');
        Route::put('kyc/{document}/review', [\App\Http\Controllers\Api\V1\KycController::class, 'review'])->name('kyc.review');
        Route::get('kyc/pending', [\App\Http\Controllers\Api\V1\KycController::class, 'pending'])->name('kyc.pending');

        // Loan Products
        Route::apiResource('loan-types', \App\Http\Controllers\Api\V1\LoanTypeController::class);
        Route::apiResource('loan-plans', \App\Http\Controllers\Api\V1\LoanPlanController::class);
        Route::get('loan-plans/{plan}/calculate', [\App\Http\Controllers\Api\V1\LoanPlanController::class, 'calculate'])->name('loan-plans.calculate');

        // Loans
        Route::apiResource('loans', \App\Http\Controllers\Api\V1\LoanController::class);
        Route::post('loans/{loan}/approve', [\App\Http\Controllers\Api\V1\LoanController::class, 'approve'])->name('loans.approve');
        Route::post('loans/{loan}/disburse', [\App\Http\Controllers\Api\V1\LoanController::class, 'disburse'])->name('loans.disburse');
        Route::post('loans/{loan}/deny', [\App\Http\Controllers\Api\V1\LoanController::class, 'deny'])->name('loans.deny');
        Route::post('loans/{loan}/freeze', [\App\Http\Controllers\Api\V1\LoanController::class, 'freeze'])->name('loans.freeze');
        Route::get('loans/{loan}/schedule', [\App\Http\Controllers\Api\V1\LoanController::class, 'schedule'])->name('loans.schedule');

        // Payments
        Route::apiResource('payments', \App\Http\Controllers\Api\V1\PaymentController::class)->only(['index', 'store', 'show']);
        Route::get('loans/{loan}/payments', [\App\Http\Controllers\Api\V1\PaymentController::class, 'byLoan'])->name('loans.payments');

        // Fund Management
        Route::get('funds/balance', [\App\Http\Controllers\Api\V1\FundController::class, 'balance'])->name('funds.balance');
        Route::get('funds/transactions', [\App\Http\Controllers\Api\V1\FundController::class, 'transactions'])->name('funds.transactions');
        Route::apiResource('funds/deposits', \App\Http\Controllers\Api\V1\FundDepositController::class)->names('funds.deposits');

        // Expenses
        Route::apiResource('expenses', \App\Http\Controllers\Api\V1\ExpenseController::class);
        Route::post('expenses/{expense}/approve', [\App\Http\Controllers\Api\V1\ExpenseController::class, 'approve'])->name('expenses.approve');
        Route::apiResource('expense-categories', \App\Http\Controllers\Api\V1\ExpenseCategoryController::class);

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('active-loans', [\App\Http\Controllers\Api\V1\ReportController::class, 'activeLoans'])->name('active-loans');
            Route::get('overdue', [\App\Http\Controllers\Api\V1\ReportController::class, 'overdue'])->name('overdue');
            Route::get('collections', [\App\Http\Controllers\Api\V1\ReportController::class, 'collections'])->name('collections');
            Route::get('disbursements', [\App\Http\Controllers\Api\V1\ReportController::class, 'disbursements'])->name('disbursements');
            Route::get('fund-statement', [\App\Http\Controllers\Api\V1\ReportController::class, 'fundStatement'])->name('fund-statement');
        });

        // Staff
        Route::apiResource('staff', \App\Http\Controllers\Api\V1\StaffController::class);
        Route::post('staff/{staff}/reset-password', [\App\Http\Controllers\Api\V1\StaffController::class, 'resetPassword'])->name('staff.reset-password');

        // Settings
        Route::get('settings', [\App\Http\Controllers\Api\V1\SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [\App\Http\Controllers\Api\V1\SettingController::class, 'update'])->name('settings.update');

        // Notifications
        Route::get('notifications', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index'])->name('notifications.index');
        Route::put('notifications/{id}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markRead'])->name('notifications.read');

        // Dashboard
        Route::get('dashboard/kpis', [\App\Http\Controllers\Api\V1\DashboardController::class, 'kpis'])->name('dashboard.kpis');
        Route::get('dashboard/charts/{type}', [\App\Http\Controllers\Api\V1\DashboardController::class, 'chart'])->name('dashboard.charts');
    });

    // ─── Borrower Protected Routes ────────────────────
    Route::prefix('me')->name('borrower.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\Borrower\ProfileController::class, 'show'])->name('profile');
        Route::get('loans', [\App\Http\Controllers\Api\V1\Borrower\ProfileController::class, 'loans'])->name('loans');
        Route::get('payments', [\App\Http\Controllers\Api\V1\Borrower\ProfileController::class, 'payments'])->name('payments');
    });

    // ─── Mobile Money Webhooks (HMAC signature verified, no auth) ───
    Route::prefix('webhooks')->name('webhooks.')->group(function () {
        Route::post('airtel', [\App\Http\Controllers\Webhook\AirtelWebhookController::class, 'handle'])->name('airtel');
        Route::post('mtn', [\App\Http\Controllers\Webhook\MtnWebhookController::class, 'handle'])->name('mtn');
        Route::post('zamtel', [\App\Http\Controllers\Webhook\ZamtelWebhookController::class, 'handle'])->name('zamtel');
        Route::post('flutterwave', [\App\Http\Controllers\Webhook\FlutterwaveWebhookController::class, 'handle'])->name('flutterwave');
        Route::post('pawapay', [\App\Http\Controllers\Webhook\PawaPayWebhookController::class, 'handle'])->name('pawapay');
    });
});

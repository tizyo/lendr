<?php

use App\Http\Controllers\Admin\BulkController;
use Illuminate\Support\Facades\Route;

Route::prefix('bulk')->name('bulk.')->group(function () {
    Route::get('import-borrowers', [BulkController::class, 'importBorrowersPage'])->name('import-borrowers');
    Route::post('import-borrowers', [BulkController::class, 'importBorrowers'])->name('import-borrowers.upload');

    Route::get('loans', [BulkController::class, 'bulkLoansPage'])->name('loans');
    Route::post('loans/approve', [BulkController::class, 'bulkApproveLoans'])->name('loans.approve');
    Route::post('loans/disburse', [BulkController::class, 'bulkDisburseLoans'])->name('loans.disburse');

    Route::get('payments', [BulkController::class, 'batchPaymentsPage'])->name('payments');
    Route::post('payments', [BulkController::class, 'batchPayments'])->name('payments.upload');
});

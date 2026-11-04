<?php

use App\Http\Controllers\Admin\BulkController;
use Illuminate\Support\Facades\Route;

Route::prefix('bulk')->name('bulk.')->group(function () {
    Route::get('import-borrowers', [BulkController::class, 'importBorrowersPage'])->name('import-borrowers')->middleware('permission:borrowers.create');
    Route::post('import-borrowers', [BulkController::class, 'importBorrowers'])->name('import-borrowers.upload')->middleware('permission:borrowers.create');

    Route::get('loans', [BulkController::class, 'bulkLoansPage'])->name('loans')->middleware('permission:loans.view');
    Route::post('loans/approve', [BulkController::class, 'bulkApproveLoans'])->name('loans.approve')->middleware('permission:loans.approve');
    Route::post('loans/disburse', [BulkController::class, 'bulkDisburseLoans'])->name('loans.disburse')->middleware('permission:loans.disburse');

    Route::get('payments', [BulkController::class, 'batchPaymentsPage'])->name('payments')->middleware('permission:payments.create');
    Route::post('payments', [BulkController::class, 'batchPayments'])->name('payments.upload')->middleware('permission:payments.create');
});

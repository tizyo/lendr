<?php

use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\PdfController;
use Illuminate\Support\Facades\Route;

Route::resource('loans', LoanController::class)->only(['index', 'create', 'store', 'show']);

Route::prefix('loans/{loan}')->name('loans.')->group(function () {
    Route::get('pdf/agreement',    [PdfController::class, 'loanAgreement'])->name('pdf.agreement');
    Route::get('pdf/schedule',     [PdfController::class, 'repaymentSchedule'])->name('pdf.schedule');
    Route::get('pdf/disbursement', [PdfController::class, 'disbursementLetter'])->name('pdf.disbursement');
});

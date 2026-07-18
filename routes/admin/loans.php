<?php

use App\Http\Controllers\Admin\LoanController;
use App\Http\Controllers\Admin\PdfController;
use Illuminate\Support\Facades\Route;

Route::resource('loans', LoanController::class)->only(['index', 'show'])->middleware('permission:loans.view');
Route::resource('loans', LoanController::class)->only(['create', 'store'])->middleware('permission:loans.create');

Route::prefix('loans/{loan}')->name('loans.')->middleware('permission:loans.view')->group(function () {
    Route::get('pdf/application', [PdfController::class, 'loanApplication'])->name('pdf.application');
    Route::get('pdf/agreement', [PdfController::class, 'loanAgreement'])->name('pdf.agreement');
    Route::get('pdf/schedule', [PdfController::class, 'repaymentSchedule'])->name('pdf.schedule');
    Route::get('pdf/disbursement', [PdfController::class, 'disbursementLetter'])->name('pdf.disbursement');
});

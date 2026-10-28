<?php

use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PdfController;
use Illuminate\Support\Facades\Route;

// Before the resource, so "bank-deposits" isn't taken for a {payment} id.
Route::inertia('payments/bank-deposits', 'payments/BankDeposits')->name('payments.bank-deposits')->middleware('permission:payments.view');

Route::resource('payments', PaymentController::class)->only(['index', 'show'])->middleware('permission:payments.view');

Route::get('payments/{payment}/pdf/receipt', [PdfController::class, 'paymentReceipt'])->name('payments.pdf.receipt')->middleware('permission:payments.view');

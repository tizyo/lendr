<?php

use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PdfController;
use Illuminate\Support\Facades\Route;

Route::resource('payments', PaymentController::class)->only(['index', 'show']);

Route::get('payments/{payment}/pdf/receipt', [PdfController::class, 'paymentReceipt'])->name('payments.pdf.receipt');

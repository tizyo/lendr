<?php

use App\Http\Controllers\Admin\LoanTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('loan-types')->name('loan-types.')->middleware('permission:loan_products.view')->group(function () {
    Route::get('/', [LoanTypeController::class, 'index'])->name('index');
});

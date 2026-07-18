<?php

use App\Http\Controllers\Admin\LoanGroupAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('loan-groups')->name('loan-groups.')->group(function () {
    Route::get('/', [LoanGroupAdminController::class, 'index'])->name('index');
    Route::get('/{group}', [LoanGroupAdminController::class, 'show'])->name('show');
});

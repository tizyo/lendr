<?php

use App\Http\Controllers\Admin\FundController;
use Illuminate\Support\Facades\Route;

Route::middleware('permission:funds.view')->group(function () {
    Route::get('funds', [FundController::class, 'index'])->name('funds.index');
    Route::get('funds/deposits', [FundController::class, 'deposits'])->name('funds.deposits.index');
});

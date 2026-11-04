<?php

use App\Http\Controllers\Admin\ExchangeRateController;
use Illuminate\Support\Facades\Route;

Route::prefix('exchange-rates')->name('exchange-rates.')->middleware('permission:accounting.view')->group(function () {
    Route::get('/', [ExchangeRateController::class, 'index'])->name('index');
    Route::post('/', [ExchangeRateController::class, 'store'])->name('store')->middleware('permission:accounting.manage');
    Route::put('/{exchangeRate}', [ExchangeRateController::class, 'update'])->name('update')->middleware('permission:accounting.manage');
    Route::delete('/{exchangeRate}', [ExchangeRateController::class, 'destroy'])->name('destroy')->middleware('permission:accounting.manage');
});

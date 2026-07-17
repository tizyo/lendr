<?php

use App\Http\Controllers\Admin\ExchangeRateController;
use Illuminate\Support\Facades\Route;

Route::prefix('exchange-rates')->name('exchange-rates.')->group(function () {
    Route::get('/',                          [ExchangeRateController::class, 'index'])->name('index');
    Route::post('/',                         [ExchangeRateController::class, 'store'])->name('store');
    Route::put('/{exchangeRate}',            [ExchangeRateController::class, 'update'])->name('update');
    Route::delete('/{exchangeRate}',         [ExchangeRateController::class, 'destroy'])->name('destroy');
});

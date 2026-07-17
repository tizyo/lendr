<?php

use App\Http\Controllers\Admin\SavingsAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('savings')->name('savings.')->group(function () {
    Route::get('/',                          [SavingsAdminController::class, 'index'])->name('index');
    Route::get('/{savings}',                 [SavingsAdminController::class, 'show'])->name('show');
});

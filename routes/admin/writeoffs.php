<?php

use App\Http\Controllers\Admin\WriteoffAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('writeoffs')->name('writeoffs.')->middleware('permission:loans.view')->group(function () {
    Route::get('/', [WriteoffAdminController::class, 'index'])->name('index');
});

<?php

use App\Http\Controllers\Admin\WriteoffAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('writeoffs')->name('writeoffs.')->group(function () {
    Route::get('/', [WriteoffAdminController::class, 'index'])->name('index');
});

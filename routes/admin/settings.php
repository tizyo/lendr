<?php

use App\Http\Controllers\Admin\SettingController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')->name('settings.')->middleware('permission:settings.view')->group(function () {
    Route::get('/', [SettingController::class, 'index'])->name('index');
    Route::put('/', [SettingController::class, 'update'])->name('update')->middleware('permission:settings.edit');
    Route::post('/test-email', [SettingController::class, 'testEmail'])->name('test-email')->middleware('permission:settings.edit');
});

<?php

use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\StaffProfileController;
use Illuminate\Support\Facades\Route;

// Staff profile (authenticated user's own profile)
Route::get('/staff/profile',          [StaffProfileController::class, 'show'])->name('staff.profile');
Route::put('/staff/profile',          [StaffProfileController::class, 'update'])->name('staff.profile.update');
Route::put('/staff/profile/password', [StaffProfileController::class, 'changePassword'])->name('staff.profile.password');

Route::prefix('staff')->name('staff.')->middleware('permission:staff.view')->group(function () {
    Route::get('/',                              [StaffController::class, 'index'])->name('index');
    Route::post('/',                             [StaffController::class, 'store'])->name('store')->middleware('permission:staff.create');
    Route::put('/{staff}',                       [StaffController::class, 'update'])->name('update')->middleware('permission:staff.edit');
    Route::delete('/{staff}',                    [StaffController::class, 'destroy'])->name('destroy')->middleware('permission:staff.delete');
    Route::post('/{staff}/reset-password',       [StaffController::class, 'resetPassword'])->name('reset-password')->middleware('permission:staff.reset_password');
    Route::put('/{staff}/toggle-status',         [StaffController::class, 'toggleStatus'])->name('toggle-status')->middleware('permission:staff.edit');
});

<?php

use App\Http\Controllers\Admin\BranchController;
use Illuminate\Support\Facades\Route;

Route::prefix('branches')->name('branches.')->middleware('permission:branches.view')->group(function () {
    Route::get('/', [BranchController::class, 'index'])->name('index');
    Route::post('/', [BranchController::class, 'store'])->name('store')->middleware('permission:branches.manage');
    Route::put('/{branch}', [BranchController::class, 'update'])->name('update')->middleware('permission:branches.manage');
    Route::delete('/{branch}', [BranchController::class, 'destroy'])->name('destroy')->middleware('permission:branches.manage');
});

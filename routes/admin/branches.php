<?php

use App\Http\Controllers\Admin\BranchController;
use Illuminate\Support\Facades\Route;

Route::prefix('branches')->name('branches.')->group(function () {
    Route::get('/',           [BranchController::class, 'index'])->name('index');
    Route::post('/',          [BranchController::class, 'store'])->name('store');
    Route::put('/{branch}',   [BranchController::class, 'update'])->name('update');
    Route::delete('/{branch}',[BranchController::class, 'destroy'])->name('destroy');
});

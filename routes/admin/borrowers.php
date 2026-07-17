<?php

use App\Http\Controllers\Admin\BorrowerController;
use App\Http\Controllers\Admin\PdfController;
use Illuminate\Support\Facades\Route;

Route::prefix('borrowers')->name('borrowers.')->middleware('permission:borrowers.view')->group(function () {
    Route::get('/', [BorrowerController::class, 'index'])->name('index');
    Route::get('/create', [BorrowerController::class, 'create'])->name('create')->middleware('permission:borrowers.create');
    Route::post('/', [BorrowerController::class, 'store'])->name('store')->middleware('permission:borrowers.create');
    Route::get('/{borrower}', [BorrowerController::class, 'show'])->name('show');
    Route::get('/{borrower}/edit', [BorrowerController::class, 'edit'])->name('edit')->middleware('permission:borrowers.edit');
    Route::put('/{borrower}', [BorrowerController::class, 'update'])->name('update')->middleware('permission:borrowers.edit');
    Route::delete('/{borrower}', [BorrowerController::class, 'destroy'])->name('destroy')->middleware('permission:borrowers.delete');
    Route::post('/{borrower}/blacklist', [BorrowerController::class, 'toggleBlacklist'])->name('blacklist')->middleware('permission:borrowers.blacklist');
    Route::get('/{borrower}/pdf/statement', [PdfController::class, 'accountStatement'])->name('pdf.statement')->middleware('permission:borrowers.export');
});

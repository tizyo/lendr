<?php

use App\Http\Controllers\Admin\BorrowerController;
use App\Http\Controllers\Admin\PdfController;
use Illuminate\Support\Facades\Route;

Route::prefix('borrowers')->name('borrowers.')->group(function () {
    Route::get('/', [BorrowerController::class, 'index'])->name('index');
    Route::get('/create', [BorrowerController::class, 'create'])->name('create');
    Route::post('/', [BorrowerController::class, 'store'])->name('store');
    Route::get('/{borrower}', [BorrowerController::class, 'show'])->name('show');
    Route::get('/{borrower}/edit', [BorrowerController::class, 'edit'])->name('edit');
    Route::put('/{borrower}', [BorrowerController::class, 'update'])->name('update');
    Route::delete('/{borrower}', [BorrowerController::class, 'destroy'])->name('destroy');
    Route::post('/{borrower}/blacklist', [BorrowerController::class, 'toggleBlacklist'])->name('blacklist');
    Route::get('/{borrower}/pdf/statement', [PdfController::class, 'accountStatement'])->name('pdf.statement');
});

<?php

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\PdfController;
use Illuminate\Support\Facades\Route;

Route::get('reports', [ReportController::class, 'index'])->name('reports.index')->middleware('permission:reports.view');

Route::prefix('reports')->name('reports.')->middleware('permission:reports.view')->group(function () {
    Route::get('par',          [ReportController::class, 'par'])->name('par');
    Route::get('officer',      [ReportController::class, 'loanOfficer'])->name('officer');
    Route::get('collections',  [ReportController::class, 'collections'])->name('collections');
    Route::get('pnl',          [ReportController::class, 'pnl'])->name('pnl');
});

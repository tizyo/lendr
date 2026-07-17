<?php

use App\Http\Controllers\Admin\CollectionController;
use Illuminate\Support\Facades\Route;

Route::prefix('collections')->name('collections.')->group(function () {
    Route::get('/',              [CollectionController::class, 'index'])->name('index');
    Route::get('/stats',         [CollectionController::class, 'stats'])->name('stats');
    Route::get('/{loan}',        [CollectionController::class, 'show'])->name('show');
    Route::post('/{loan}/logs',  [CollectionController::class, 'store'])->name('logs.store');
});

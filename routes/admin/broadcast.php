<?php

use App\Http\Controllers\Admin\BroadcastController;
use Illuminate\Support\Facades\Route;

Route::prefix('broadcast')->name('broadcast.')->middleware('permission:notifications.broadcast')->group(function () {
    Route::get('/',    [BroadcastController::class, 'index'])->name('index');
    Route::post('/',   [BroadcastController::class, 'send'])->name('send');
});

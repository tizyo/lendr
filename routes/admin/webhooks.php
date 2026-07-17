<?php

use App\Http\Controllers\Admin\WebhookAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks')->name('webhooks.')->group(function () {
    Route::get('/', [WebhookAdminController::class, 'index'])->name('index');
});

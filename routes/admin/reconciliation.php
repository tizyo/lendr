<?php

use App\Http\Controllers\Admin\ReconciliationAdminController;
use Illuminate\Support\Facades\Route;

Route::get('reconciliation', [ReconciliationAdminController::class, 'index'])->name('reconciliation.index');

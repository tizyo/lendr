<?php

use App\Http\Controllers\Admin\GlLedgerAdminController;
use Illuminate\Support\Facades\Route;

Route::get('gl-ledger', [GlLedgerAdminController::class, 'index'])->name('gl-ledger.index');

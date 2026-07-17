<?php

use App\Http\Controllers\Admin\FinancialStatementAdminController;
use Illuminate\Support\Facades\Route;

Route::get('financial-statements', [FinancialStatementAdminController::class, 'index'])->name('financial-statements.index');

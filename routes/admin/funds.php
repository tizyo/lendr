<?php

use App\Http\Controllers\Admin\FundController;
use Illuminate\Support\Facades\Route;

Route::get('funds', [FundController::class, 'index'])->name('funds.index');
Route::get('funds/deposits', [FundController::class, 'deposits'])->name('funds.deposits.index');

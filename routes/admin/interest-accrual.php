<?php

use App\Http\Controllers\Admin\InterestAccrualAdminController;
use Illuminate\Support\Facades\Route;

Route::get('interest-accrual', [InterestAccrualAdminController::class, 'index'])->name('interest-accrual.index');

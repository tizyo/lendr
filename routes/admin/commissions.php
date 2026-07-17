<?php

use App\Http\Controllers\Admin\CommissionAdminController;
use Illuminate\Support\Facades\Route;

Route::get('commissions', [CommissionAdminController::class, 'index'])->name('commissions.index');

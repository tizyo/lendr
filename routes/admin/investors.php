<?php

use App\Http\Controllers\Admin\InvestorAdminController;
use Illuminate\Support\Facades\Route;

Route::get('investors', [InvestorAdminController::class, 'index'])->name('investors.index');

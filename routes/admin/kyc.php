<?php

use App\Http\Controllers\Admin\KycController;
use Illuminate\Support\Facades\Route;

Route::get('kyc', [KycController::class, 'index'])->name('kyc.index');

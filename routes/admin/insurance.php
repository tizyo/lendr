<?php

use App\Http\Controllers\Admin\InsuranceAdminController;
use Illuminate\Support\Facades\Route;

Route::get('insurance', [InsuranceAdminController::class, 'index'])->name('insurance.index');

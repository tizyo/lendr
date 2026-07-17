<?php

use App\Http\Controllers\Admin\AnalyticsAdminController;
use Illuminate\Support\Facades\Route;

Route::get('analytics', [AnalyticsAdminController::class, 'index'])->name('analytics.index');

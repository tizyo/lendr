<?php

use App\Http\Controllers\Admin\StaffTargetAdminController;
use Illuminate\Support\Facades\Route;

Route::get('staff-targets', [StaffTargetAdminController::class, 'index'])->name('staff-targets.index');

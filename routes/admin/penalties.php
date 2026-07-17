<?php

use App\Http\Controllers\Admin\PenaltyAdminController;
use Illuminate\Support\Facades\Route;

Route::get('penalties', [PenaltyAdminController::class, 'index'])->name('penalties.index');

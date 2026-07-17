<?php

use App\Http\Controllers\Admin\LeadAdminController;
use Illuminate\Support\Facades\Route;

Route::get('leads', [LeadAdminController::class, 'index'])->name('leads.index');

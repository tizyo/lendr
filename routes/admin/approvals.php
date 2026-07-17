<?php

use App\Http\Controllers\Admin\ApprovalAdminController;
use Illuminate\Support\Facades\Route;

Route::get('approvals', [ApprovalAdminController::class, 'index'])->name('approvals.index');

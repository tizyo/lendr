<?php

use App\Http\Controllers\Admin\ApiClientAdminController;
use Illuminate\Support\Facades\Route;

Route::get('api-clients', [ApiClientAdminController::class, 'index'])->name('api-clients.index')->middleware(['permission:integrations.manage', 'plan.feature:api_access']);

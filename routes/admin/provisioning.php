<?php

use App\Http\Controllers\Admin\ProvisioningAdminController;
use Illuminate\Support\Facades\Route;

Route::get('provisioning', [ProvisioningAdminController::class, 'index'])->name('provisioning.index');

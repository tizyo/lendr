<?php

use App\Http\Controllers\Admin\HotDealAdminController;
use Illuminate\Support\Facades\Route;

Route::get('hot-deals', [HotDealAdminController::class, 'index'])->name('hot-deals.index');

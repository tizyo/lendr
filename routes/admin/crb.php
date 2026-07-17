<?php

use App\Http\Controllers\Admin\CrbAdminController;
use Illuminate\Support\Facades\Route;

Route::get('crb', [CrbAdminController::class, 'index'])->name('crb.index');

<?php

use App\Http\Controllers\Admin\CollectionCaseAdminController;
use Illuminate\Support\Facades\Route;

Route::get('collection-cases', [CollectionCaseAdminController::class, 'index'])->name('collection-cases.index');

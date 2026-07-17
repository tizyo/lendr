<?php

use App\Http\Controllers\Admin\FeaturedItemAdminController;
use Illuminate\Support\Facades\Route;

Route::get('featured-items', [FeaturedItemAdminController::class, 'index'])->name('featured-items.index');

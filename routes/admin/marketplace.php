<?php

use App\Http\Controllers\Admin\MarketplaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('marketplace')->name('marketplace.')->middleware('permission:marketplace.view')->group(function () {
    Route::get('/', [MarketplaceController::class, 'index'])->name('index');
    Route::get('{id}/interests', [MarketplaceController::class, 'interests'])->name('interests');
    Route::post('{id}/expire', [MarketplaceController::class, 'expire'])->name('expire')->middleware('permission:marketplace.manage');

    // Public loan product marketplace (cross-tenant)
    Route::get('products', [MarketplaceController::class, 'publicProducts'])->name('products');
    Route::post('products/{id}/unpublish', [MarketplaceController::class, 'unpublishProduct'])->name('products.unpublish')->middleware('permission:marketplace.manage');

    // Repo marketplace (repossessed items)
    Route::get('repo-items', [MarketplaceController::class, 'repoItems'])->name('repo-items');

    // One listing: details, offers so far, make an offer (the page loads it from /api/v1).
    Route::get('{id}', fn (int $id) => \Inertia\Inertia::render('marketplace/Show', ['id' => $id]))->whereNumber('id')->name('show');
});

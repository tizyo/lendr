<?php

use App\Http\Controllers\Admin\MarketplaceController;
use Illuminate\Support\Facades\Route;

Route::prefix('marketplace')->name('marketplace.')->group(function () {
    Route::get('/', [MarketplaceController::class, 'index'])->name('index');
    Route::get('{id}/interests', [MarketplaceController::class, 'interests'])->name('interests');
    Route::post('{id}/expire', [MarketplaceController::class, 'expire'])->name('expire');

    // Public loan product marketplace (cross-tenant)
    Route::get('products', [MarketplaceController::class, 'publicProducts'])->name('products');
    Route::post('products/{id}/unpublish', [MarketplaceController::class, 'unpublishProduct'])->name('products.unpublish');

    // Repo marketplace (repossessed items)
    Route::get('repo-items', [MarketplaceController::class, 'repoItems'])->name('repo-items');
});

<?php

use App\Http\Controllers\Pwa\AppController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Borrower PWA Routes  (/app prefix — registered in web.php)
|--------------------------------------------------------------------------
| Entry point: resources/js/pwa.js  →  resolves pages from pwa/pages/
*/

// ─── Guest routes ─────────────────────────────────────────────────────────
Route::get('/',       [AppController::class, 'login'])->name('auth.login');
Route::get('/otp',    [AppController::class, 'showOtp'])->name('auth.otp');
Route::get('/set-pin', [AppController::class, 'showSetPin'])->name('auth.set-pin');

// ─── Authenticated routes ──────────────────────────────────────────────────
Route::get('/dashboard', [AppController::class, 'dashboard'])->name('dashboard');
Route::get('/loans',     [AppController::class, 'loans'])->name('loans');
Route::get('/payments',       [AppController::class, 'payments'])->name('payments');
Route::get('/notifications',  [AppController::class, 'notifications'])->name('notifications');
Route::get('/profile',        [AppController::class, 'profile'])->name('profile');

// KYC
Route::get('/kyc',        [AppController::class, 'kycOnboarding'])->name('kyc.onboarding');
Route::get('/kyc/status', [AppController::class, 'kycStatus'])->name('kyc.status');

// Marketplace (borrower)
Route::get('/marketplace',          [AppController::class, 'marketplaceListings'])->name('marketplace.listings');
Route::get('/marketplace/create',   [AppController::class, 'marketplaceCreate'])->name('marketplace.create');
Route::get('/marketplace/products', [AppController::class, 'publicProducts'])->name('marketplace.public-products');

// Repo marketplace — public browsing (no auth required)
Route::get('/repo',           [AppController::class, 'repoBrowse'])->name('repo.browse');
Route::get('/repo/{id}',      [AppController::class, 'repoShow'])->name('repo.show');

// Repo marketplace — ghost user auth
Route::get('/repo/auth/login',    [AppController::class, 'ghostLogin'])->name('repo.auth.login');
Route::get('/repo/auth/verify',   [AppController::class, 'ghostVerify'])->name('repo.auth.verify');

// Repo marketplace — ghost user cart (auth required at client level)
Route::get('/repo/cart',          [AppController::class, 'repoCart'])->name('repo.cart');
Route::get('/repo/enquiries',     [AppController::class, 'repoMyEnquiries'])->name('repo.my-enquiries');

// Loan application, detail & payment (P16/P20)
Route::get('/loans/apply',    [AppController::class, 'loanApply'])->name('loans.apply');
Route::get('/loans/{id}/pay', [AppController::class, 'loanPay'])->name('loans.pay');
Route::get('/loans/{id}',     [AppController::class, 'loanShow'])->name('loans.show');

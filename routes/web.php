<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Central / Landlord Routes (lendr.app)
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    return Inertia::render('Landing/Home');
});

/*
|--------------------------------------------------------------------------
| Admin Staff Auth Routes (tenant subdomains)
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Authenticated Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Borrowers
    require __DIR__.'/admin/borrowers.php';

    // Loans
    require __DIR__.'/admin/loans.php';

    // Payments
    require __DIR__.'/admin/payments.php';

    // Fund Management
    require __DIR__.'/admin/funds.php';

    // Expenses
    require __DIR__.'/admin/expenses.php';

    // Reports
    require __DIR__.'/admin/reports.php';

    // Staff Management
    require __DIR__.'/admin/staff.php';

    // Settings
    require __DIR__.'/admin/settings.php';
});

/*
|--------------------------------------------------------------------------
| Borrower PWA Routes (/app prefix)
|--------------------------------------------------------------------------
*/
Route::prefix('app')->name('pwa.')->group(function () {
    require __DIR__.'/pwa.php';
});

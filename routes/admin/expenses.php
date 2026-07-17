<?php

use App\Http\Controllers\Admin\ExpenseCategoryController;
use App\Http\Controllers\Admin\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::get('expenses',                              [ExpenseController::class, 'index'])->name('expenses.index');
Route::get('expenses/{expense}',                    [ExpenseController::class, 'show'])->name('expenses.show');
Route::get('expense-categories',                    [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');

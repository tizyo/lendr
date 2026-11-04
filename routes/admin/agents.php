<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Agent / DSA network, risk policies and notification templates: the pages talk to
// /api/v1 themselves (permission-checked there too); these only serve them.
Route::get('agents', fn () => Inertia::render('agents/Index'))->name('agents.index')->middleware('permission:sales.view');
Route::get('risk-policies', fn () => Inertia::render('risk-policies/Index'))->name('risk-policies.index')->middleware('permission:risk.view');
Route::get('notification-templates', fn () => Inertia::render('notification-templates/Index'))->name('notification-templates.index')->middleware('permission:settings.view');

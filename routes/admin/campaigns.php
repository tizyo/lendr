<?php

use App\Http\Controllers\Admin\CampaignAdminController;
use Illuminate\Support\Facades\Route;

Route::get('campaigns', [CampaignAdminController::class, 'index'])->name('campaigns.index')->middleware('permission:sales.view');

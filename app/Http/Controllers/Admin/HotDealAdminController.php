<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HotDealAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('hot-deals/Index');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FinancialStatementAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('financial-statements/Index');
    }
}

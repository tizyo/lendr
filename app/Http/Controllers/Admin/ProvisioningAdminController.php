<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ProvisioningAdminController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('provisioning/Index');
    }
}

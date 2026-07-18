<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\LoanWriteoff;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WriteoffAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $query = LoanWriteoff::with(['loan.borrower', 'writtenOffBy'])
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at');

        return Inertia::render('writeoffs/Index', [
            'writeoffs' => $query->paginate(20)->withQueryString(),
        ]);
    }
}

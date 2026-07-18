<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\KycDocument;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KycController extends Controller
{
    public function index(Request $request): Response
    {
        $documents = KycDocument::query()
            ->with('borrower:id,borrower_number,first_name,last_name,phone')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->document_type, fn ($q, $t) => $q->where('document_type', $t))
            ->when($request->search, fn ($q, $s) => $q->whereHas('borrower', fn ($bq) => $bq->where('first_name', 'like', "%{$s}%")
                ->orWhere('last_name', 'like', "%{$s}%")
                ->orWhere('borrower_number', 'like', "%{$s}%")
                ->orWhere('phone', 'like', "%{$s}%"),
            ))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($d) => [
                'id' => $d->id,
                'document_type' => $d->document_type,
                'status' => $d->status->value,
                'status_label' => $d->status->label(),
                'file_url' => $d->file_url,
                'mime_type' => $d->mime_type,
                'rejection_reason' => $d->rejection_reason,
                'expires_at' => $d->expires_at?->toDateString(),
                'created_at' => $d->created_at->format('d M Y'),
                'borrower' => [
                    'id' => $d->borrower->id,
                    'borrower_number' => $d->borrower->borrower_number,
                    'full_name' => $d->borrower->full_name,
                    'phone' => $d->borrower->phone,
                ],
            ]);

        return Inertia::render('kyc/Index', [
            'documents' => $documents,
            'filters' => $request->only('status', 'document_type', 'search'),
        ]);
    }
}

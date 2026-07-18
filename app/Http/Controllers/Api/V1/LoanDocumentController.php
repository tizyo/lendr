<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LoanDocumentController extends BaseApiController
{
    /**
     * GET /api/v1/loans/{loan}/documents
     */
    public function index(Loan $loan): JsonResponse
    {
        $documents = $loan->documents()
            ->with('uploadedBy:id,name')
            ->latest()
            ->get()
            ->map(fn ($d) => $this->format($d));

        return $this->success($documents);
    }

    /**
     * POST /api/v1/loans/{loan}/documents
     */
    public function store(Request $request, Loan $loan): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
            'document_type' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:200'],
        ]);

        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $path = $file->store("loans/{$loan->id}/documents", 'public');

        $document = LoanDocument::create([
            'loan_id' => $loan->id,
            'document_type' => $request->document_type,
            'title' => $request->title ?? $fileName,
            'file_path' => Storage::url($path),
            'file_name' => $fileName,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        return $this->success($this->format($document->load('uploadedBy')), 'Document uploaded.', 201);
    }

    /**
     * DELETE /api/v1/loans/{loan}/documents/{document}
     */
    public function destroy(Loan $loan, LoanDocument $document): JsonResponse
    {
        if ($document->loan_id !== $loan->id) {
            return $this->error('Document not found for this loan.', 404);
        }

        // Remove file from storage
        $storagePath = Str::after($document->file_path, Storage::url(''));
        if (Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->delete($storagePath);
        }

        $document->delete();

        return $this->success(null, 'Document deleted.');
    }

    private function format(LoanDocument $d): array
    {
        return [
            'id' => $d->id,
            'document_type' => $d->document_type,
            'title' => $d->title,
            'file_name' => $d->file_name,
            'file_path' => $d->file_path,
            'mime_type' => $d->mime_type,
            'file_size' => $d->file_size,
            'uploaded_by' => $d->relationLoaded('uploadedBy') ? $d->uploadedBy?->name : null,
            'created_at' => $d->created_at->format('d M Y H:i'),
        ];
    }
}

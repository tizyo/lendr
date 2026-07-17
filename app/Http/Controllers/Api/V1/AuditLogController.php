<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends BaseApiController
{
    /**
     * GET /api/v1/audit-log
     * Paginated activity log with optional filters.
     */
    public function index(Request $request): JsonResponse
    {
        $logs = Activity::query()
            ->with('causer:id,name')
            ->when($request->subject_type, fn ($q, $t) => $q->where('subject_type', 'like', "%{$t}%"))
            ->when($request->causer_id,    fn ($q, $id) => $q->where('causer_id', $id))
            ->when($request->event,        fn ($q, $e) => $q->where('description', $e))
            ->when($request->date_from, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->date_to,   fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest()
            ->paginate($request->integer('per_page', 30));

        return $this->paginated($logs, fn (Activity $log) => [
            'id'           => $log->id,
            'log_name'     => $log->log_name,
            'description'  => $log->description,
            'subject_type' => class_basename($log->subject_type ?? ''),
            'subject_id'   => $log->subject_id,
            'causer'       => $log->causer ? ['id' => $log->causer->id, 'name' => $log->causer->name] : null,
            'properties'   => $log->properties,
            'created_at'   => $log->created_at->toDateTimeString(),
        ]);
    }

    /**
     * GET /api/v1/audit-log/export
     * Download audit log as CSV (max 5000 rows, same filters as index).
     */
    public function export(Request $request): Response
    {
        $logs = Activity::query()
            ->with('causer:id,name')
            ->when($request->subject_type, fn ($q, $t) => $q->where('subject_type', 'like', "%{$t}%"))
            ->when($request->causer_id,    fn ($q, $id) => $q->where('causer_id', $id))
            ->when($request->event,        fn ($q, $e) => $q->where('description', $e))
            ->when($request->date_from, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->date_to,   fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest()
            ->limit(5000)
            ->get();

        $header = implode(',', ['Date', 'Event', 'Subject', 'Subject ID', 'Performed By', 'Log Name']);

        $rows = $logs->map(fn (Activity $log) => implode(',', [
            $log->created_at->toDateTimeString(),
            '"'.addcslashes($log->description ?? '', '"').'"',
            '"'.addcslashes(class_basename($log->subject_type ?? ''), '"').'"',
            $log->subject_id ?? '',
            '"'.addcslashes($log->causer?->name ?? 'System', '"').'"',
            '"'.addcslashes($log->log_name ?? '', '"').'"',
        ]));

        $csv      = $header."\n".$rows->implode("\n");
        $filename = 'audit-log-'.now()->format('Y-m-d').'.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

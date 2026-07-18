<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = Activity::query()
            ->with('causer:id,name')
            ->when($request->causer_id, fn ($q, $id) => $q->where('causer_id', $id))
            ->when($request->event, fn ($q, $e) => $q->where('description', $e))
            ->when($request->subject_type, fn ($q, $t) => $q->where('subject_type', 'like', "%{$t}%"))
            ->when($request->date_from, fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('audit-log/Index', [
            'logs' => $logs->through(fn (Activity $log) => [
                'id' => $log->id,
                'description' => $log->description,
                'subject_type' => class_basename($log->subject_type ?? ''),
                'subject_id' => $log->subject_id,
                'causer' => $log->causer ? $log->causer->name : 'System',
                'properties' => $log->properties,
                'created_at' => $log->created_at->toDateTimeString(),
            ]),
            'filters' => $request->only('causer_id', 'event', 'subject_type', 'date_from', 'date_to'),
        ]);
    }
}

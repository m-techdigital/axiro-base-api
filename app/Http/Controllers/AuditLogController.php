<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $query = AuditLog::query()
            ->when($request->filled('audit_type'), fn ($q) => $q->where('audit_type', $request->string('audit_type')))
            ->when($request->filled('event_type'), fn ($q) => $q->where('event_type', $request->string('event_type')))
            ->when($request->filled('risk_level'), fn ($q) => $q->where('risk_level', $request->string('risk_level')))
            ->when($request->filled('actor_type'), fn ($q) => $q->where('actor_type', $request->string('actor_type')))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->string('entity_type')))
            ->when($request->filled('entity_id'), fn ($q) => $q->where('entity_id', (string) $request->input('entity_id')))
            ->when($request->filled('request_id'), fn ($q) => $q->where('request_id', $request->string('request_id')))
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $keyword = '%'.$request->string('keyword').'%';
                $q->where(fn ($inner) => $inner->where('title', 'like', $keyword)->orWhere('description', 'like', $keyword)->orWhere('path', 'like', $keyword));
            })
            ->latest('id');
        $page = $query->paginate(min(100, max(1, $request->integer('per_page', 30))));
        return success_response($page->items(), 'Thành công', 200, ['pagination' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function show(AuditLog $auditLog)
    {
        return success_response($auditLog);
    }

    public function statistics()
    {
        $base = AuditLog::query();
        return success_response([
            'total' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('created_at', today())->count(),
            'validation_failures' => (clone $base)->where('audit_type', 'validation')->count(),
            'high_risk' => (clone $base)->where('risk_level', 'high')->count(),
            'by_type' => (clone $base)->selectRaw('audit_type, COUNT(*) total')->groupBy('audit_type')->pluck('total', 'audit_type'),
        ]);
    }
}

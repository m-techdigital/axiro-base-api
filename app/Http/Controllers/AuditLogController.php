<?php

namespace App\Http\Controllers;

use App\Http\Requests\Common\ListQueryRequest;
use App\Models\AuditLog;
use App\Support\Query\AppliesListQuery;

class AuditLogController extends Controller
{
    use AppliesListQuery;

    public function index(ListQueryRequest $request)
    {
        $query = $this->applyListFilters(
            AuditLog::query(),
            $request,
            ['title', 'description', 'path'],
            ['audit_type', 'event_type', 'risk_level', 'actor_type', 'entity_type', 'entity_id', 'request_id'],
            ['id', 'created_at', 'risk_level', 'audit_type'],
            'id',
        );

        $page = $query->paginate($request->perPage(30));

        return pagy_success_response($page);
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

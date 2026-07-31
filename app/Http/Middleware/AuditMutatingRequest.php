<?php

namespace App\Http\Middleware;

use App\Services\AuditTrailService;
use App\Support\AuditPayloadSanitizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditMutatingRequest
{
    private const METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (!in_array($request->method(), self::METHODS, true) || $response->getStatusCode() === 422) return;
        if (Str::is(['api/v1/login', 'api/v1/refresh', 'api/v1/auth/customer/login', 'api/v1/auth/customer/refresh'], trim($request->path(), '/'))) return;
        $started = (float) $request->attributes->get('audit_started_at', microtime(true));
        app(AuditTrailService::class)->log([
            'audit_type' => 'system_operation',
            'event_type' => 'http_mutation',
            'risk_level' => $response->getStatusCode() >= 400 ? 'warning' : 'normal',
            'status_code' => $response->getStatusCode(),
            'title' => sprintf('%s %s', $request->method(), '/'.ltrim($request->path(), '/')),
            'description' => sprintf('Yêu cầu thay đổi dữ liệu hoàn tất với mã trạng thái %d.', $response->getStatusCode()),
            'metadata' => [
                'duration_ms' => max(0, (int) round((microtime(true) - $started) * 1000)),
                'payload' => AuditPayloadSanitizer::sanitize($request->all()),
                'route_parameters' => AuditPayloadSanitizer::sanitize($request->route()?->parameters() ?? []),
            ],
        ]);
    }
}

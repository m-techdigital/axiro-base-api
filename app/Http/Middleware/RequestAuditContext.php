<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestAuditContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = trim((string) $request->headers->get('X-Request-ID'));
        $correlationId = trim((string) $request->headers->get('X-Correlation-ID'));
        $request->attributes->set('request_id', Str::isUuid($requestId) ? $requestId : (string) Str::uuid());
        $request->attributes->set('correlation_id', Str::isUuid($correlationId) ? $correlationId : (string) Str::uuid());
        $request->attributes->set('audit_started_at', microtime(true));
        $response = $next($request);
        $response->headers->set('X-Request-ID', $request->attributes->get('request_id'));
        $response->headers->set('X-Correlation-ID', $request->attributes->get('correlation_id'));
        return $response;
    }
}

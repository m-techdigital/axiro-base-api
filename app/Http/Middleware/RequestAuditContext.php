<?php

namespace App\Http\Middleware;

use App\Support\CorrelationContext;
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
        $requestId = Str::isUuid($requestId) ? strtolower($requestId) : (string) Str::uuid();
        $correlationId = CorrelationContext::normalize($correlationId) ?? $requestId;

        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('correlation_id', $correlationId);
        $request->attributes->set('audit_started_at', microtime(true));

        return CorrelationContext::run($correlationId, function () use ($request, $next, $requestId, $correlationId): Response {
            $response = $next($request);
            $response->headers->set('X-Request-ID', $requestId);
            $response->headers->set('X-Correlation-ID', $correlationId);

            return $response;
        });
    }
}

<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
class ApiAuthenticate { public function handle(Request $request, Closure $next): Response { if (! auth('api')->check()) return error_response('Unauthenticated', null, 401); return $next($request); } }

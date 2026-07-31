<?php
namespace App\Http\Middleware; use Closure; use Illuminate\Http\Request; use Symfony\Component\HttpFoundation\Response;
class CustomerAuthenticate{public function handle(Request $request,Closure $next):Response{if(!auth('customer_api')->check())return error_response('Unauthenticated',null,401);return $next($request);}}

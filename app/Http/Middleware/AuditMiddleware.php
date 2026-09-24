<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuditMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('DELETE')) {
            // Audit logging handled in controllers for more specific context
        }
        
        return $response;
    }
}

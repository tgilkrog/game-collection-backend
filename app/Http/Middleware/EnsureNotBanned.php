<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotBanned
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/logout')) {
            return $next($request);
        }

        abort_if($request->user()?->is_banned, 403, 'Your account has been suspended.', [
            'X-Account-Banned' => 'true',
        ]);

        return $next($request);
    }
}

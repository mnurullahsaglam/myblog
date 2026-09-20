<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

final class ThrottlePasswordReset
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('password.email', 'password.update')) {
            return $next($request);
        }

        return resolve(ThrottleRequests::class)->handle($request, $next, 'password-reset');
    }
}

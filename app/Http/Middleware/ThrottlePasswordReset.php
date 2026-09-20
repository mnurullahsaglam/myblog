<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limits the password reset routes.
 *
 * Fortify exposes limiter keys for login, two-factor and passkeys but not for
 * password reset, and its routes are registered by the package rather than by
 * this application, so the limit is applied here instead. Without it, the reset
 * form is an open mail relay pointed at whoever the attacker names.
 */
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

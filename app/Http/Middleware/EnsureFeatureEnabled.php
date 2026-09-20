<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Access\AccessProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a route with a feature flag.
 *
 * A flagged-off route is a 404 rather than a 403, for the same reason a hidden
 * area is: a screen that is not ready for you should be indistinguishable from
 * one that does not exist.
 */
final class EnsureFeatureEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        abort_unless(app(AccessProfile::class)->feature($flag), 404);

        return $next($request);
    }
}

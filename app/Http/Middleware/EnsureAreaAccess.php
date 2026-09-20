<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Area;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards a route group with an area.
 *
 * Laravel's own `can:` middleware resolves its extra arguments against the
 * route's parameters, so it cannot carry a literal area name; this does, and
 * reads better in the route file for it.
 */
final class EnsureAreaAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $resolved = Area::tryFrom($area);

        // An area the enum does not know is a typo in the route file. It closes
        // the group rather than opening it, and the access matrix test is what
        // reports it.
        abort_if($resolved === null, 404);

        abort_unless($request->user()?->can('access-area', $resolved) ?? false, 404);

        return $next($request);
    }
}

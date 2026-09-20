<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Area;
use App\Support\Access\AccessProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAreaAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $area): Response
    {
        $resolved = Area::tryFrom($area);

        abort_if($resolved === null, 404);

        abort_unless(resolve(AccessProfile::class)->canAccess($resolved), 404);

        return $next($request);
    }
}

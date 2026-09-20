<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Access\AccessProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureFeatureEnabled
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $flag): Response
    {
        abort_unless(resolve(AccessProfile::class)->feature($flag), 404);

        return $next($request);
    }
}

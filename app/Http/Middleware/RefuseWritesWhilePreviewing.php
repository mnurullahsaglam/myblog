<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Access\AccessProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A preview reads; it never writes.
 *
 * Applied to every route rather than only the admin ones, so a preview cannot
 * write through the public site either. Leaving the preview is the one write it
 * has to allow.
 */
final class RefuseWritesWhilePreviewing
{
    /**
     * @var array<int, string>
     */
    private const array SAFE = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previewing = resolve(AccessProfile::class)->isPreviewing();
        $leaving = $request->routeIs('admin.preview.destroy') || $request->routeIs('logout');

        abort_if(
            $previewing && ! $leaving && ! in_array($request->method(), self::SAFE, true),
            403,
            'This is a preview. Leave it before making changes.',
        );

        return $next($request);
    }
}

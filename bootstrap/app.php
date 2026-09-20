<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceIdempotency;
use App\Http\Middleware\EnsureAreaAccess;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RefuseWritesWhilePreviewing;
use App\Http\Middleware\ThrottlePasswordReset;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            RefuseWritesWhilePreviewing::class,
            ThrottlePasswordReset::class,
        ]);

        $middleware->api(append: [EnforceIdempotency::class]);

        $middleware->alias([
            'area' => EnsureAreaAccess::class,
            'feature' => EnsureFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})->create();

<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

/**
 * Every v1 route, crossed with both roles, authenticated by token rather than by
 * session. Generated from the router for the same reason the panel's matrix is:
 * a route added later is covered the day it appears.
 *
 * @return array<string, array{string, string, array<int, string>}>
 */
function apiRoutes(): array
{
    static $cached = null;

    if (is_array($cached)) {
        return $cached;
    }

    $app = require __DIR__.'/../../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    restore_exception_handler();
    restore_error_handler();

    $cases = [];

    foreach ($app->make('router')->getRoutes() as $route) {
        $name = $route->getName();

        if (! is_string($name) || ! str_starts_with($name, 'api.v1.')) {
            continue;
        }

        if (str_starts_with($name, 'api.v1.tokens.')) {
            continue;
        }

        $method = in_array('GET', $route->methods(), true) ? 'GET' : $route->methods()[0];

        $cases[$method.' '.$name] = [$method, $name, $route->parameterNames()];
    }

    return $cached = $cases;
}

/**
 * Which area each route belongs to, by name prefix.
 */
function apiAreaOf(string $routeName): ?string
{
    $map = [
        'api.v1.posts.' => 'blog',
        'api.v1.categories.' => 'general',
        'api.v1.books.' => 'library',
        'api.v1.writers.' => 'library',
        'api.v1.publishers.' => 'library',
        'api.v1.utility-accounts.' => 'utilities',
        'api.v1.utility-bills.' => 'utilities',
        'api.v1.clients.' => 'work',
        'api.v1.projects.' => 'work',
        'api.v1.repositories.' => 'work',
        'api.v1.invoices.' => 'work',
        'api.v1.waka-time-summaries.' => 'work',
        'api.v1.incomes.' => 'budget',
        'api.v1.expenses.' => 'budget',
        'api.v1.debts.' => 'budget',
    ];

    foreach ($map as $prefix => $area) {
        if (str_starts_with($routeName, $prefix)) {
            return $area;
        }
    }

    return null;
}

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
});

/**
 * Compared against the owner's answer rather than a fixed status, for the same
 * reason the panel's matrix is: some routes refuse for reasons of their own, and
 * a hand-written list of those exceptions rots.
 */
it('gives a member what the admin gets inside her areas, and nothing outside them', function (string $method, string $name, array $parameterNames): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $ownerStatus = apiAs($owner)
        ->json($method, route($name, routeParameters($parameterNames, $owner)))
        ->status();

    $member = User::factory()->member()->create(['email' => 'her@example.test']);
    $memberStatus = apiAs($member)
        ->json($method, route($name, routeParameters($parameterNames, $member)))
        ->status();

    $area = apiAreaOf($name);
    $hers = $area === null || in_array($area, ['budget', 'utilities', 'library'], true);

    $hers
        ? expect($memberStatus)->toBe($ownerStatus, "{$name} answered her differently from the owner")
        : expect($memberStatus)->toBe(404, "{$name} is a {$area} route and should not exist for her");
})->with(fn (): array => apiRoutes());

it('turns away a request with no token', function (string $method, string $name, array $parameterNames): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);

    app('auth')->forgetGuards();

    $this->json($method, route($name, routeParameters($parameterNames, $owner)))
        ->assertUnauthorized();
})->with(fn (): array => apiRoutes());

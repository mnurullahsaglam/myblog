<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

/**
 * Routes hidden by a feature flag rather than by an area.
 *
 * The matrix compares the member's answer to the owner's, and a flagged route
 * answers differently on purpose: it is not finished, so she does not get it
 * yet. FeatureFlagTest covers these instead. Keep this list short; if it grows,
 * flags are being used for something they were not meant for.
 *
 * @var array<int, string>
 */
const FLAG_GATED = [
    'admin.budget-limits.index',
];

/**
 * Routes whose guard is the controller rather than an area.
 *
 * Starting a preview is refused to a member with a 404 and to the owner with a
 * 422 when no role is supplied, so the two answers differ on purpose and the
 * matrix cannot read them. PreviewTest covers them instead.
 *
 * @var array<int, string>
 */
const CONTROLLER_GUARDED = [
    'admin.preview.store',
];

/**
 * Every admin route, crossed with both roles.
 *
 * Built from the router rather than hand-listed, so a route added next year is
 * covered the day it appears and a route that forgets its area group fails the
 * moment it exists.
 *
 * @return array<string, array{string, string, array<int, string>}>
 */
function adminRoutes(): array
{
    static $cached = null;

    if (is_array($cached)) {
        return $cached;
    }

    // Pest resolves datasets before the test application exists, so the router
    // is bootstrapped here deliberately. The alternative is a hand-written list
    // of routes, which is exactly what this test is meant to replace.
    $app = require __DIR__.'/../../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    // Bootstrapping registers global error and exception handlers that this
    // throwaway application never tears down, which PHPUnit reports as a risky
    // test. The router is all that is wanted here, so the handlers go back.
    restore_exception_handler();
    restore_error_handler();

    $cases = [];

    foreach ($app->make('router')->getRoutes() as $route) {
        $name = $route->getName();

        if (! is_string($name) || ! str_starts_with($name, 'admin.')) {
            continue;
        }

        // The signed download route cannot be reached without a signature, and
        // its guard is the signature, not an area.
        if ($name === 'admin.exports.download') {
            continue;
        }

        if (in_array($name, FLAG_GATED, true) || in_array($name, CONTROLLER_GUARDED, true)) {
            continue;
        }

        $method = in_array('GET', $route->methods(), true) ? 'GET' : $route->methods()[0];

        $cases[$method.' '.$name] = [$method, $name, $route->parameterNames()];
    }

    return $cached = $cases;
}

/**
 * Which area each route belongs to, by name prefix. Anything unlisted is a
 * route every signed-in user may reach.
 */
function areaOf(string $routeName): ?string
{
    $map = [
        'admin.posts.' => 'blog',
        'admin.categories.' => 'general',
        'admin.settings' => 'general',
        'admin.people.' => 'general',
        'admin.publishers.' => 'library',
        'admin.writers.' => 'library',
        'admin.books.' => 'library',
        'admin.utility-accounts.' => 'utilities',
        'admin.utility-bills.' => 'utilities',
        'admin.clients.' => 'work',
        'admin.projects.' => 'work',
        'admin.repositories.' => 'work',
        'admin.invoices.' => 'work',
        'admin.coding-dashboard' => 'work',
        'admin.tasks.' => 'work',
        'admin.waka-time-summaries.' => 'work',
        'admin.incomes.' => 'budget',
        'admin.expenses.' => 'budget',
        'admin.debts.' => 'budget',
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
 * The member's answer is compared against the owner's rather than against a
 * fixed status.
 *
 * Some routes 404 for reasons of their own — a bulk-update route on a resource
 * with nothing bulk editable, for one — and asserting "not 404" would need a
 * hand-written list of those exceptions, which is exactly the kind of list that
 * rots. Comparing the two users states the requirement directly: inside her
 * areas she gets what he gets; outside them the route does not exist.
 *
 * Both calls build their own records, so a destroy in the first does not leave
 * the second looking at a missing row.
 */
it('gives a member what the admin gets inside her areas, and nothing outside them', function (string $method, string $name, array $parameterNames): void {
    $owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $ownerStatus = $this->actingAs($owner)
        ->call($method, route($name, routeParameters($parameterNames, $owner)))
        ->status();

    $member = User::factory()->member()->create(['email' => 'her@example.test']);
    $memberStatus = $this->actingAs($member)
        ->call($method, route($name, routeParameters($parameterNames, $member)))
        ->status();

    $area = areaOf($name);
    $hers = $area === null || in_array($area, ['budget', 'utilities', 'library'], true);

    $hers
        ? expect($memberStatus)->toBe($ownerStatus, "{$name} answered her differently from the owner")
        : expect($memberStatus)->toBe(404, "{$name} is a {$area} route and should not exist for her");
})->with(fn (): array => adminRoutes());

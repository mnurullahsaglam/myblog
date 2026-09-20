<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

/**
 * @var array<int, string>
 */
const FLAG_GATED = [
    'admin.budget-limits.index',
];

/**
 * @var array<int, string>
 */
const CONTROLLER_GUARDED = [
    'admin.preview.store',
];

/**
 * @return array<string, array{string, string, array<int, string>}>
 */
function adminRoutes(): array
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

        if (! is_string($name) || ! str_starts_with($name, 'admin.')) {
            continue;
        }

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

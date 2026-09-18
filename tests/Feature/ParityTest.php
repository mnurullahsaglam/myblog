<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Navigation;
use App\Services\ExchangeRateService;
use App\Services\GitHubService;
use Illuminate\Support\Facades\Route;

/**
 * Fails if any Filament feature lacks a replacement. This is the gate that has
 * to pass before the old panel is deleted.
 */
beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));

    $github = Mockery::mock(GitHubService::class);
    $github->shouldIgnoreMissing();
    app()->instance(GitHubService::class, $github);

    $exchange = Mockery::mock(ExchangeRateService::class);
    $exchange->shouldReceive('convert')->andReturnUsing(fn (float $amount): float => $amount);
    $exchange->shouldIgnoreMissing();
    app()->instance(ExchangeRateService::class, $exchange);
});

it('has an index route for all thirteen resources', function (string $routeName): void {
    expect(Route::has($routeName))->toBeTrue("Missing route {$routeName}");
})->with([
    'admin.posts.index',
    'admin.categories.index',
    'admin.books.index',
    'admin.writers.index',
    'admin.publishers.index',
    'admin.clients.index',
    'admin.projects.index',
    'admin.repositories.index',
    'admin.invoices.index',
    'admin.incomes.index',
    'admin.expenses.index',
    'admin.debts.index',
    'admin.waka-time-summaries.index',
]);

it('replaces every Filament panel feature', function (string $routeName): void {
    expect(Route::has($routeName))->toBeTrue("Missing route {$routeName}");
})->with([
    'admin.dashboard',
    'admin.coding-dashboard',
    'admin.tasks.board',
    'admin.tasks.move',
    'admin.search',
    'admin.settings',
    'admin.settings.update',
    'admin.profile',
    'admin.exports.store',
    'admin.exports.download',
    'admin.notifications.read-all',
    'admin.debts.pay',
    'login',
    'logout',
]);

it('serves every admin page without error', function (string $routeName): void {
    $this->get(route($routeName))->assertOk();
})->with([
    'admin.dashboard',
    'admin.coding-dashboard',
    'admin.tasks.board',
    'admin.settings',
    'admin.profile',
    'admin.posts.index',
    'admin.categories.index',
    'admin.books.index',
    'admin.writers.index',
    'admin.publishers.index',
    'admin.clients.index',
    'admin.projects.index',
    'admin.repositories.index',
    'admin.invoices.index',
    'admin.incomes.index',
    'admin.expenses.index',
    'admin.debts.index',
    'admin.waka-time-summaries.index',
]);

it('serves every create form without error', function (string $routeName): void {
    $this->get(route($routeName))->assertOk();
})->with([
    'admin.posts.create',
    'admin.categories.create',
    'admin.books.create',
    'admin.writers.create',
    'admin.publishers.create',
    'admin.clients.create',
    'admin.projects.create',
    'admin.repositories.create',
    'admin.invoices.create',
    'admin.incomes.create',
    'admin.expenses.create',
    'admin.debts.create',
]);

it('keeps the public site working', function (): void {
    auth()->logout();

    $this->get('/')->assertOk();
    $this->get('/books')->assertOk();
});

it('points every navigation item at a real route', function (): void {
    foreach (Navigation::clusters() as $cluster) {
        expect($cluster['items'])->not->toBeEmpty("Cluster {$cluster['label']} has no items");

        foreach ($cluster['items'] as $item) {
            expect(Route::has($item['route']))->toBeTrue("Missing route {$item['route']}");
        }
    }
});

it('covers every cluster the old panel had', function (): void {
    $labels = collect(Navigation::clusters())->pluck('label')->all();

    expect($labels)->toBe(['Blog', 'Budget', 'Work', 'Library', 'General']);
});

it('still exposes the wakatime oauth routes', function (): void {
    expect(Route::has('wakatime.connect'))->toBeTrue();
    expect(Route::has('wakatime.callback'))->toBeTrue();
});

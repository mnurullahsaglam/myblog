<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\AssertionFailedError;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->admin = User::factory()->create(['email' => 'admin@example.test']);
});

function inertiaVersion(): string
{
    return (string) resolve(HandleInertiaRequests::class)->version(request());
}

it('renders an inertia page with props', function (): void {
    Route::middleware('web')->get('/__inertia-probe', fn () => inertia('Dashboard', ['answer' => 42]));

    $this->actingAs($this->admin)
        ->get('/__inertia-probe')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Dashboard')
            ->where('answer', 42)
        );
});

it('fails loudly when a page component file is missing', function (): void {
    Route::middleware('web')->get('/__inertia-missing', fn () => inertia('NoSuchPage'));

    $this->actingAs($this->admin)->get('/__inertia-missing')->assertOk();

    expect(fn () => $this->get('/__inertia-missing')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('NoSuchPage')))
        ->toThrow(AssertionFailedError::class);
});

it('does not evaluate a closure prop excluded from a partial reload', function (): void {
    $calls = 0;

    Route::middleware('web')->get('/__inertia-partial', function () use (&$calls) {
        return inertia('Dashboard', [
            'cheap' => 'always',
            'expensive' => function () use (&$calls): string {
                $calls++;

                return 'computed';
            },
        ]);
    });

    $this->actingAs($this->admin)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => inertiaVersion(),
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'cheap',
        ])
        ->get('/__inertia-partial')
        ->assertOk();

    expect($calls)->toBe(0, 'A closure prop should not run when a partial reload excludes it.');
});

it('does evaluate a closure prop included in a partial reload', function (): void {
    $calls = 0;

    Route::middleware('web')->get('/__inertia-partial-included', function () use (&$calls) {
        return inertia('Dashboard', [
            'expensive' => function () use (&$calls): string {
                $calls++;

                return 'computed';
            },
        ]);
    });

    $this->actingAs($this->admin)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => inertiaVersion(),
            'X-Inertia-Partial-Component' => 'Dashboard',
            'X-Inertia-Partial-Data' => 'expensive',
        ])
        ->get('/__inertia-partial-included')
        ->assertOk();

    expect($calls)->toBe(1);
});

it('serves the admin dashboard to the admin', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->component('Dashboard'));
});

it('redirects a guest away from the panel', function (): void {
    $this->get(route('admin.dashboard'))->assertRedirect();
});

it('forbids a user with no areas', function (): void {
    $this->actingAs(userWithoutRole('nobody@example.test'))
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

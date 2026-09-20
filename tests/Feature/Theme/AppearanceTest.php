<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Support\AdminNotifier;
use App\Support\Theme\Appearance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->admin = User::factory()->create(['email' => 'admin@example.test']);
});

it('defaults to khaki and system', function (): void {
    expect(Appearance::accent())->toBe('khaki')
        ->and(Appearance::colorScheme())->toBe('system');
});

it('reads stored values', function (): void {
    Setting::set('appearance', 'accent', 'emerald');
    Setting::set('appearance', 'color_scheme', 'light');

    expect(Appearance::accent())->toBe('emerald')
        ->and(Appearance::colorScheme())->toBe('light');
});

it('falls back to khaki for an unknown stored accent', function (): void {
    Setting::set('appearance', 'accent', 'chartreuse');

    expect(Appearance::accent())->toBe('khaki');
});

it('falls back to system for an unknown stored scheme', function (): void {
    Setting::set('appearance', 'color_scheme', 'neon');

    expect(Appearance::colorScheme())->toBe('system');
});

it('exposes an array for inertia', function (): void {
    Setting::set('appearance', 'accent', 'rose');
    Setting::set('appearance', 'color_scheme', 'dark');

    expect(Appearance::toArray())->toBe(['accent' => 'rose', 'colorScheme' => 'dark']);
});

it('shares auth, appearance, navigation and env with every inertia response', function (): void {
    Setting::set('appearance', 'accent', 'sky');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('auth.user.email', 'admin@example.test')
            ->where('appearance.accent', 'sky')
            ->where('appearance.colorScheme', 'system')
            // Structure, not a count: pinning the number forbids ever adding a cluster.
            ->has('navigation.0.items.0.route')
            ->where('env.isProduction', false)
        );
});

it('shares a null user for a guest', function (): void {
    Route::middleware('web')->get('/__guest-probe', fn () => inertia('Dashboard'));

    $this->get('/__guest-probe')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('auth.user', null));
});

it('never shares the password hash', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('auth.user', fn (Collection $user): bool => $user->keys()->all() === ['id', 'name', 'email'])
        );
});

it('shares a flashed notification', function (): void {
    Route::middleware('web')->get('/__flash-probe', function () {
        resolve(AdminNotifier::class)->success('Saved');

        return inertia('Dashboard');
    });

    $this->actingAs($this->admin)
        ->get('/__flash-probe')
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('flash.notification.title', 'Saved')
            ->where('flash.notification.variant', 'success')
        );
});

it('inlines the accent css in the root view', function (): void {
    Setting::set('appearance', 'accent', 'emerald');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertSee('--p-primary-400:#34D399;', escape: false);
});

it('marks the document with the stored colour scheme', function (): void {
    Setting::set('appearance', 'color_scheme', 'dark');

    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertSee('data-color-scheme="dark"', escape: false);
});

it('leaves system scheme for the client script to resolve', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.dashboard'))
        ->assertSee('data-color-scheme="system"', escape: false)
        ->assertSee('prefers-color-scheme: dark', escape: false);
});

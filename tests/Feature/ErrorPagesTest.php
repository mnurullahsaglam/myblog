<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Support\Theme\Palette;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    config(['app.debug' => false]);

    Route::get('/__abort/{code}', fn (string $code) => abort((int) $code));
});

/**
 * Every status the application publishes a page for. The list is the directory,
 * so a page added later without a test fails this rather than going unnoticed.
 */
dataset('statuses', [401, 402, 403, 404, 419, 429, 500, 503]);

it('renders a page for every published status', function (int $code): void {
    $this->get('/__abort/'.$code)
        ->assertStatus($code)
        ->assertSee((string) $code, escape: false)
        ->assertSee(config('app.name'), escape: false);
})->with('statuses');

it('publishes a page for every status the framework would otherwise handle', function (): void {
    $published = collect(glob(resource_path('views/errors/*.blade.php')) ?: [])
        ->map(fn (string $path): string => basename($path, '.blade.php'))
        ->reject(fn (string $name): bool => $name === 'layout')
        ->sort()
        ->values()
        ->all();

    expect($published)->toBe(['401', '402', '403', '404', '419', '429', '500', '503']);
});

it('carries no framework styling', function (int $code): void {
    $html = $this->get('/__abort/'.$code)->getContent();

    expect($html)->not->toContain('#636b6f')
        ->and($html)->not->toContain('normalize.css')
        ->and($html)->not->toContain('bg-gray-100')
        ->and($html)->not->toContain('Whoops');
})->with('statuses');

it('paints the panel surfaces', function (): void {
    Setting::set('appearance', 'color_scheme', 'dark');

    $html = $this->get('/__abort/404')->getContent();

    expect($html)->toContain('--mb-canvas:#0D0E11;')
        ->and($html)->toContain('--mb-border:#272B35;')
        ->and($html)->toContain('Inter')
        ->and($html)->toContain('JetBrains Mono');
});

it('follows the signed-in reader rather than the household', function (): void {
    Setting::set('appearance', 'color_scheme', 'light');
    Setting::set('appearance', 'accent', 'amber');

    $user = User::factory()->create(['preferences' => ['color_scheme' => 'dark', 'accent' => 'violet']]);

    $html = $this->actingAs($user)->get('/__abort/404')->getContent();

    expect($html)->toContain('--mb-canvas:#0D0E11;')
        ->and($html)->toContain('--mb-accent:#A78BFA;')
        ->and($html)->not->toContain('prefers-color-scheme');
});

/**
 * A reader who has not chosen gets both schemes, because the page renders before
 * any script could ask the browser which one it wants.
 */
it('ships both schemes when the reader follows the system', function (): void {
    Setting::set('appearance', 'color_scheme', 'system');

    $html = $this->get('/__abort/404')->getContent();

    expect($html)->toContain('--mb-canvas:#F7F7F5;')
        ->and($html)->toContain('@media (prefers-color-scheme: dark)')
        ->and($html)->toContain('--mb-canvas:#0D0E11;')
        ->and($html)->toContain('content="light dark"');
});

it('sends the reader back to the panel', function (): void {
    $this->get('/__abort/404')->assertSee(url('/'), escape: false);
});

/**
 * An error page renders when the session, the database or both are already gone.
 * Resolving the reader must not be able to turn one failure into two.
 */
it('falls back to the built-in theme when the reader cannot be resolved', function (): void {
    Setting::set('appearance', 'color_scheme', 'dark');

    $this->app->extend('auth', function (): never {
        throw new RuntimeException('session store not set on request');
    });

    $resolved = Palette::forErrorPage();

    expect($resolved['palette']->scheme)->toBe('light')
        ->and($resolved['palette']->accent)->toBe(Palette::of('light', 'khaki')->accent)
        ->and($resolved['dark'])->toBeNull();
});

it('says something specific rather than repeating the status name', function (): void {
    $this->get('/__abort/404')->assertSee('Nothing here', escape: false);
    $this->get('/__abort/419')->assertSee('The page expired', escape: false);
    $this->get('/__abort/429')->assertSee('Slow down', escape: false);
    $this->get('/__abort/503')->assertSee('Down for maintenance', escape: false);
});

/**
 * The uppercase labels are English copy on a page the browser is told is Turkish,
 * where CSS would render "Service Unavailable" as "SERVİCE UNAVAİLABLE". Marking
 * those two elements English is what keeps the transform honest.
 */
it('marks its uppercase labels English so Turkish casing leaves them alone', function (): void {
    app()->setLocale('tr');

    $html = $this->get('/__abort/503')->getContent();

    expect($html)->toContain('<html lang="tr">')
        ->and($html)->toContain('class="code" lang="en"')
        ->and($html)->toContain('lang="en">Back to the panel</a>');
});

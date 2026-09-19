<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
    Storage::fake('public');
});

it('renders the settings page with appearance defaults', function (): void {
    $this->get(route('admin.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('Settings')
            ->where('settings.appearance.accent', 'khaki')
            ->where('settings.appearance.color_scheme', 'system')
            ->has('accents', 9)
            ->has('schemes', 3)
        );
});

it('offers every accent with a swatch', function (): void {
    $this->get(route('admin.settings'))
        ->assertInertia(function (AssertableInertia $page): void {
            $accents = collect($page->toArray()['props']['accents']);

            expect($accents->pluck('name'))->toContain('khaki', 'emerald', 'zinc')
                ->and($accents->firstWhere('name', 'khaki')['swatch'])->toBe('#C9BE6E');
        });
});

it('loads stored settings into the form', function (): void {
    Setting::set('site_info', 'site_name', 'My Blog');
    Setting::set('social', 'github_url', 'https://github.com/example');

    $this->get(route('admin.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('settings.site_info.site_name', 'My Blog')
            ->where('settings.social.github_url', 'https://github.com/example')
        );
});

it('saves the accent and colour scheme', function (): void {
    $this->put(route('admin.settings.update'), [
        'appearance' => ['accent' => 'emerald', 'color_scheme' => 'dark'],
    ])->assertRedirect(route('admin.settings'));

    expect(Setting::get('appearance', 'accent'))->toBe('emerald')
        ->and(Setting::get('appearance', 'color_scheme'))->toBe('dark');
});

it('rejects an unknown accent or scheme', function (): void {
    $this->from(route('admin.settings'))
        ->put(route('admin.settings.update'), [
            'appearance' => ['accent' => 'chartreuse', 'color_scheme' => 'neon'],
        ])
        ->assertSessionHasErrors(['appearance.accent', 'appearance.color_scheme']);
});

it('changes the inlined accent css after saving', function (): void {
    $this->put(route('admin.settings.update'), ['appearance' => ['accent' => 'rose']]);

    $this->get(route('admin.dashboard'))->assertSee('--p-primary-400:#FB7185;', escape: false);
});

it('shares the new appearance with inertia after saving', function (): void {
    $this->put(route('admin.settings.update'), [
        'appearance' => ['accent' => 'sky', 'color_scheme' => 'light'],
    ]);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('appearance.accent', 'sky')
            ->where('appearance.colorScheme', 'light')
        );
});

it('saves site information', function (): void {
    $this->put(route('admin.settings.update'), [
        'site_info' => [
            'site_name' => 'My Blog',
            'site_description' => 'Notes and code',
            'site_url' => 'https://example.test',
            'admin_email' => 'me@example.test',
        ],
    ])->assertSessionHasNoErrors();

    expect(Setting::get('site_info', 'site_name'))->toBe('My Blog')
        ->and(Setting::get('site_info', 'site_url'))->toBe('https://example.test');
});

it('rejects an invalid url or email', function (): void {
    $this->from(route('admin.settings'))
        ->put(route('admin.settings.update'), [
            'site_info' => ['site_url' => 'not a url', 'admin_email' => 'not an email'],
            'social' => ['github_url' => 'also not a url'],
        ])
        ->assertSessionHasErrors(['site_info.site_url', 'site_info.admin_email', 'social.github_url']);
});

it('round-trips keywords through json', function (): void {
    $this->put(route('admin.settings.update'), [
        'meta' => ['meta_keywords' => ['rust', 'laravel', 'vue']],
    ])->assertSessionHasNoErrors();

    $this->get(route('admin.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('settings.meta.meta_keywords', ['rust', 'laravel', 'vue'])
        );
});

it('gives an empty keyword list when none is stored', function (): void {
    $this->get(route('admin.settings'))
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page->where('settings.meta.meta_keywords', []));
});

it('stores an uploaded logo', function (): void {
    $this->put(route('admin.settings.update'), [
        'branding' => ['logo' => UploadedFile::fake()->image('logo.png')],
    ]);

    $stored = Setting::get('branding', 'logo');

    expect($stored)->toStartWith('settings/branding/');
    Storage::disk('public')->assertExists($stored);
});

it('keeps a stored upload when no new file is sent', function (): void {
    Setting::set('branding', 'logo', 'settings/branding/existing.png', 'file');

    $this->put(route('admin.settings.update'), ['site_info' => ['site_name' => 'Unchanged logo']]);

    expect(Setting::get('branding', 'logo'))->toBe('settings/branding/existing.png');
});

it('flashes a notification after saving', function (): void {
    $this->put(route('admin.settings.update'), ['appearance' => ['accent' => 'amber']]);

    expect(session('flash.notification'))->toHaveKey('variant', 'success');
});

it('appears in the general cluster', function (): void {
    $general = collect(Navigation::clusters())->firstWhere('label', 'General');

    expect(collect($general['items'])->pluck('route'))->toContain('admin.settings');
});

it('forbids a non-admin', function (): void {
    $this->actingAs(User::factory()->create(['email' => 'nobody@example.test']));

    $this->get(route('admin.settings'))->assertForbidden();
});

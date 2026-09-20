<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Support\Theme\Appearance;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

it('falls back to the global setting when a user has no preference', function (): void {
    Setting::set('appearance', 'accent', 'amber');

    expect(Appearance::forUser($this->member)['accent'])->toBe('amber');
});

it('prefers the user over the global setting', function (): void {
    Setting::set('appearance', 'accent', 'amber');
    $this->member->update(['preferences' => ['accent' => 'rose']]);

    expect(Appearance::forUser($this->member->fresh())['accent'])->toBe('rose');
});

/**
 * The whole point: two people, two accents, at the same time.
 */
it('keeps two users on different accents', function (): void {
    $this->owner->update(['preferences' => ['accent' => 'emerald']]);
    $this->member->update(['preferences' => ['accent' => 'rose']]);

    expect(Appearance::forUser($this->owner->fresh())['accent'])->toBe('emerald')
        ->and(Appearance::forUser($this->member->fresh())['accent'])->toBe('rose');
});

it('does not change a user who has a preference when the global changes', function (): void {
    $this->member->update(['preferences' => ['accent' => 'rose']]);
    Setting::set('appearance', 'accent', 'amber');

    expect(Appearance::forUser($this->member->fresh())['accent'])->toBe('rose');
});

it('ignores an accent the application does not have', function (): void {
    $this->member->update(['preferences' => ['accent' => 'ultraviolet']]);

    expect(Appearance::forUser($this->member->fresh())['accent'])
        ->toBe(Appearance::forUser(null)['accent']);
});

it('ignores a colour scheme the application does not have', function (): void {
    $this->member->update(['preferences' => ['color_scheme' => 'sepia']]);

    expect(Appearance::forUser($this->member->fresh())['colorScheme'])
        ->toBe(Appearance::DEFAULT_SCHEME);
});

/**
 * The accent CSS is written into the Blade shell before Inertia boots, so her
 * first paint is already her colour rather than the owner's.
 */
it('carries her accent in the first paint', function (): void {
    $this->member->update(['preferences' => ['accent' => 'rose']]);
    Setting::set('appearance', 'accent', 'emerald');

    $html = $this->actingAs($this->member->fresh())->get(route('admin.dashboard'))->getContent();

    $rose = App\Support\Theme\AccentRamps::cssVariables('rose');
    $emerald = App\Support\Theme\AccentRamps::cssVariables('emerald');

    expect($html)->toContain($rose)->and($html)->not->toContain($emerald);
});

it('lets her save her own appearance from her profile', function (): void {
    $this->actingAs($this->member)
        ->put(route('admin.preferences.update'), ['accent' => 'rose', 'color_scheme' => 'dark'])
        ->assertRedirect();

    expect($this->member->fresh()->preferences)
        ->toMatchArray(['accent' => 'rose', 'color_scheme' => 'dark']);
});

it('refuses an accent that does not exist', function (): void {
    $this->actingAs($this->member)
        ->from(route('admin.profile'))
        ->put(route('admin.preferences.update'), ['accent' => 'ultraviolet'])
        ->assertSessionHasErrors('accent');
});

it('lets her clear her preference back to the default', function (): void {
    $this->member->update(['preferences' => ['accent' => 'rose']]);
    Setting::set('appearance', 'accent', 'amber');

    $this->actingAs($this->member->fresh())
        ->put(route('admin.preferences.update'), ['accent' => null, 'color_scheme' => null]);

    expect(Appearance::forUser($this->member->fresh())['accent'])->toBe('amber');
});

it('does not let her change the global setting', function (): void {
    $this->actingAs($this->member)
        ->put(route('admin.preferences.update'), ['accent' => 'rose']);

    expect(Setting::get('appearance', 'accent', 'unset'))->toBe('unset');
});

it('turns a guest away', function (): void {
    $this->put(route('admin.preferences.update'), ['accent' => 'rose'])->assertRedirect(route('login'));
});

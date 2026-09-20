<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\User;
use App\Support\Theme\Palette;

it('paints the panel surfaces exactly', function (): void {
    $dark = Palette::of('dark', 'khaki');

    expect($dark->canvas)->toBe('#0D0E11')
        ->and($dark->surface)->toBe('#15171C')
        ->and($dark->elevated)->toBe('#1A1D24')
        ->and($dark->embedded)->toBe('#121317')
        ->and($dark->border)->toBe('#272B35')
        ->and($dark->borderSubtle)->toBe('#20232B')
        ->and($dark->text)->toBe('#ECEEF2')
        ->and($dark->textSecondary)->toBe('#9096A2')
        ->and($dark->textMuted)->toBe('#5A606E');

    $light = Palette::of('light', 'khaki');

    expect($light->canvas)->toBe('#F7F7F5')
        ->and($light->surface)->toBe('#FFFFFF')
        ->and($light->elevated)->toBe('#F2F2EF')
        ->and($light->embedded)->toBe('#FAFAF8')
        ->and($light->border)->toBe('#D9D9D2')
        ->and($light->borderSubtle)->toBe('#E8E8E2')
        ->and($light->text)->toBe('#1A1B1E')
        ->and($light->textSecondary)->toBe('#5A606E')
        ->and($light->textMuted)->toBe('#8B909B');
});

/**
 * The stops come from resources/js/theme/preset.js: 500 on light, 400 on dark.
 * Taking the same stop in both schemes would put a washed-out khaki on white.
 */
it('takes the accent stop each scheme uses', function (): void {
    expect(Palette::of('light', 'khaki')->accent)->toBe('#B3A651')
        ->and(Palette::of('dark', 'khaki')->accent)->toBe('#C9BE6E')
        ->and(Palette::of('light', 'emerald')->accent)->toBe('#10B981')
        ->and(Palette::of('dark', 'emerald')->accent)->toBe('#34D399');
});

it('never puts white on an accent fill', function (): void {
    foreach (['khaki', 'amber', 'sky', 'zinc'] as $accent) {
        expect(Palette::of('light', $accent)->onAccent)->toBe('#141517')
            ->and(Palette::of('dark', $accent)->onAccent)->toBe('#141517');
    }
});

it('falls back to khaki for an accent that does not exist', function (): void {
    expect(Palette::of('dark', 'chartreuse')->accent)->toBe(Palette::of('dark', 'khaki')->accent);
});

/**
 * Neither email nor a pre-script response can ask the client what it prefers,
 * so anything that is not an explicit "dark" resolves to light.
 */
it('collapses system and nonsense to light', function (): void {
    expect(Palette::of('system', 'khaki')->scheme)->toBe('light')
        ->and(Palette::of('', 'khaki')->scheme)->toBe('light')
        ->and(Palette::of('Dark', 'khaki')->scheme)->toBe('light')
        ->and(Palette::of('dark', 'khaki')->scheme)->toBe('dark');
});

it('follows the reader over the household', function (): void {
    Setting::set('appearance', 'color_scheme', 'light');
    Setting::set('appearance', 'accent', 'amber');

    $user = User::factory()->create(['preferences' => ['color_scheme' => 'dark', 'accent' => 'violet']]);

    $palette = Palette::forUser($user);

    expect($palette->scheme)->toBe('dark')
        ->and($palette->accent)->toBe('#A78BFA');
});

it('falls back to the household for a reader with no preference', function (): void {
    Setting::set('appearance', 'color_scheme', 'dark');
    Setting::set('appearance', 'accent', 'sky');

    $palette = Palette::forUser(User::factory()->create(['preferences' => []]));

    expect($palette->scheme)->toBe('dark')
        ->and($palette->accent)->toBe('#38BDF8');
});

/**
 * An invitee has no account and so no scheme. The instruction is light, but the
 * household's accent still applies so the message looks like it came from here.
 */
it('renders light for a recipient nobody knows, in the household accent', function (): void {
    Setting::set('appearance', 'color_scheme', 'dark');
    Setting::set('appearance', 'accent', 'rose');

    $palette = Palette::forUnknownRecipient();

    expect($palette->scheme)->toBe('light')
        ->and($palette->accent)->toBe('#F43F5E');
});

it('gives an error page a dark counterpart only when the reader follows the system', function (): void {
    Setting::set('appearance', 'color_scheme', 'system');

    expect(Palette::forErrorPage()['dark'])->toBeInstanceOf(Palette::class);

    Setting::set('appearance', 'color_scheme', 'light');

    expect(Palette::forErrorPage()['dark'])->toBeNull();
});

it('emits every surface as a custom property', function (): void {
    $css = Palette::of('dark', 'khaki')->cssVariables();

    expect($css)->toContain('--mb-canvas:#0D0E11;')
        ->and($css)->toContain('--mb-text-secondary:#9096A2;')
        ->and($css)->toContain('--mb-on-accent:#141517;')
        ->and($css)->not->toContain('--mb-scheme');
});

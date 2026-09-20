<?php

declare(strict_types=1);

use App\Support\Theme\AccentRamps;

it('ships the nine accents from the spec', function (): void {
    expect(AccentRamps::names())->toBe([
        'khaki', 'amber', 'orange', 'rose', 'emerald', 'sky', 'indigo', 'violet', 'zinc',
    ]);
});

it('defaults to khaki', function (): void {
    expect(AccentRamps::DEFAULT)->toBe('khaki')
        ->and(AccentRamps::all()['khaki']['400'])->toBe('#C9BE6E');
});

it('never puts white text on an accent fill', function (): void {
    expect(AccentRamps::ON_ACCENT)->toBe('#141517');
});

it('gives every accent a complete eleven-stop ramp', function (string $name): void {
    $ramp = AccentRamps::all()[$name];

    expect(array_map(strval(...), array_keys($ramp)))
        ->toBe(['50', '100', '200', '300', '400', '500', '600', '700', '800', '900', '950'])
        ->and($ramp)->each->toMatch('/^#[0-9A-F]{6}$/');
})->with(['khaki', 'amber', 'orange', 'rose', 'emerald', 'sky', 'indigo', 'violet', 'zinc']);

it('knows which accents exist', function (): void {
    expect(AccentRamps::has('khaki'))->toBeTrue()
        ->and(AccentRamps::has('chartreuse'))->toBeFalse();
});

it('renders css custom properties for an accent', function (): void {
    $css = AccentRamps::cssVariables('khaki');

    expect($css)
        ->toStartWith(':root{')
        ->toContain('--p-primary-400:#C9BE6E;')
        ->toContain('--p-primary-950:#2A2613;')
        ->toEndWith('}');
});

it('falls back to khaki for an unknown accent', function (): void {
    expect(AccentRamps::cssVariables('chartreuse'))->toBe(AccentRamps::cssVariables('khaki'))
        ->and(AccentRamps::swatch('chartreuse'))->toBe(AccentRamps::swatch('khaki'));
});

it('exposes a swatch for the settings picker', function (): void {
    expect(AccentRamps::swatch('emerald'))->toBe('#34D399');
});

it('stays in sync with the javascript ramps', function (): void {
    $js = file_get_contents(resource_path('js/theme/ramps.js'));

    expect(AccentRamps::all())->not->toBeEmpty('No ramps to compare against the javascript');

    foreach (AccentRamps::all() as $name => $ramp) {
        expect($js)->toContain($name.': {')
            ->and($ramp)->not->toBeEmpty("Ramp {$name} has no stops");

        foreach ($ramp as $stop => $hex) {
            expect($js)->toContain("{$stop}: '{$hex}'");
        }
    }
});

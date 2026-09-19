<?php

declare(strict_types=1);

it('has no Filament, Pulse or Livewire references in application code', function (string $directory): void {
    $output = [];
    exec('grep -ril "filament\|livewire\|laravel\\\\pulse" '.base_path($directory).' 2>/dev/null', $output);

    expect($output)->toBeEmpty($directory.' still references a removed package: '.implode(', ', $output));
    // The migration that drops their tables names them, which is correct.
})->with(['app', 'config', 'routes', 'resources', 'database/seeders', 'database/factories']);

it('no longer requires the removed packages', function (string $package): void {
    /** @var array{require: array<string, string>, require-dev: array<string, string>} $composer */
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);

    expect(array_keys([...$composer['require'], ...$composer['require-dev']]))->not->toContain($package);
})->with([
    'filament/filament',
    'relaticle/flowforge',
    'pxlrbt/filament-spotlight',
    'pxlrbt/filament-environment-indicator',
    'devonab/filament-easy-footer',
    'laravel/pulse',
    'livewire/livewire',
]);

it('has none of the removed packages installed at all', function (string $package): void {
    expect(base_path('vendor/'.$package))->not->toBeDirectory();
})->with(['filament', 'livewire', 'laravel/pulse', 'relaticle']);

it('no longer has the Filament directories', function (string $path): void {
    expect(base_path($path))->not->toBeDirectory();
})->with([
    'app/Filament',
    'app/Providers/Filament',
    'resources/views/filament',
    'resources/css/filament',
    'resources/views/vendor/pulse',
]);

it('does not register a Filament panel provider', function (): void {
    /** @var array<int, class-string> $providers */
    $providers = require base_path('bootstrap/providers.php');

    expect($providers)->each->not->toContain('Filament');
});

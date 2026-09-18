<?php

declare(strict_types=1);

it('does not reference Filament', function (): void {
    expect(file_get_contents(app_path('Providers/AppServiceProvider.php')))->not->toContain('Filament');
});

it('keeps the global model and URL hardening', function (): void {
    $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

    expect($source)
        ->toContain('Model::shouldBeStrict()')
        ->toContain('Model::unguard()')
        ->toContain('DB::prohibitDestructiveCommands')
        ->toContain('URL::forceHttps');
});

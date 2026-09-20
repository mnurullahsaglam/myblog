<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;

it('does not reference Filament', function (): void {
    expect(file_get_contents(app_path('Providers/AppServiceProvider.php')))->not->toContain('Filament');
});

it('leaves models unguarded, so form definitions drive what is writable', function (): void {
    expect(Model::isUnguarded())->toBeTrue();
});

it('applies strict model rules in this environment', function (): void {
    expect(Model::preventsLazyLoading())->toBeTrue()
        ->and(Model::preventsAccessingMissingAttributes())->toBeTrue();
});

it('does not force https outside production', function (): void {
    expect(URL::route('books.index'))->toStartWith('http://');
});

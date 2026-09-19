<?php

declare(strict_types=1);

use App\Actions\Exports\ExportResource;
use App\Models\Book;
use Illuminate\Support\Facades\Storage;

function exportLines(string $path): array
{
    return array_values(array_filter(explode("\n", (string) Storage::disk('local')->get($path))));
}

it('writes a CSV to the private disk and returns its path', function (): void {
    Storage::fake('local');
    Book::factory()->count(2)->create();

    $path = resolve(ExportResource::class)->handle('books');

    expect($path)->toStartWith('exports/')
        ->and(Storage::disk('local')->exists($path))->toBeTrue();
});

it('writes a header row even when there is nothing to export', function (): void {
    Storage::fake('local');

    expect(exportLines(resolve(ExportResource::class)->handle('books')))->toHaveCount(1);
});

it('writes one row per record after the header', function (): void {
    Storage::fake('local');
    Book::factory()->count(3)->create();

    expect(exportLines(resolve(ExportResource::class)->handle('books')))->toHaveCount(4);
});

it('rejects a resource it has no exporter for', function (string $resource): void {
    expect(fn () => resolve(ExportResource::class)->handle($resource))
        ->toThrow(RuntimeException::class);
})->with(['nonsense', '', 'BOOKS', 'books ']);

it('names the unknown resource in the failure', function (): void {
    expect(fn () => resolve(ExportResource::class)->handle('nonsense'))
        ->toThrow(RuntimeException::class, 'No export is defined for [nonsense].');
});

it('exports every declared resource', function (string $resource): void {
    Storage::fake('local');

    expect(resolve(ExportResource::class)->handle($resource))->toStartWith('exports/');
})->with(array_keys(ExportResource::EXPORTS));

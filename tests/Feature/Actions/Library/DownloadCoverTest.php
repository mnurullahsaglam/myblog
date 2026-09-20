<?php

declare(strict_types=1);

use App\Actions\Library\DownloadCover;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    config(['services.open_library.contact' => 'test@example.test']);
});

it('stores a cover on the public disk and returns its path', function (): void {
    Http::fake(['covers.openlibrary.org/*' => Http::response(
        File::image('cover.jpg', 400, 600)->getContent(),
        200,
        ['Content-Type' => 'image/jpeg'],
    )]);

    $path = resolve(DownloadCover::class)->handle(12054527);

    expect($path)->toStartWith('books/')
        ->and(Storage::disk('public')->exists((string) $path))->toBeTrue();
});

it('asks Open Library not to substitute a placeholder', function (): void {
    Http::fake(['covers.openlibrary.org/*' => Http::response(
        File::image('cover.jpg')->getContent(), 200, ['Content-Type' => 'image/jpeg'],
    )]);

    resolve(DownloadCover::class)->handle(12054527);

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'default=false'));
});

it('returns null when the cover does not exist', function (): void {
    Http::fake(['covers.openlibrary.org/*' => Http::response('', 404)]);

    expect(resolve(DownloadCover::class)->handle(999_999_999))->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuses a response that is not an image', function (): void {
    Http::fake(['covers.openlibrary.org/*' => Http::response(
        '<html>nope</html>', 200, ['Content-Type' => 'text/html'],
    )]);

    expect(resolve(DownloadCover::class)->handle(12054527))->toBeNull()
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuses a cover larger than the form would accept', function (): void {
    Http::fake(['covers.openlibrary.org/*' => Http::response(
        str_repeat('x', 6 * 1024 * 1024), 200, ['Content-Type' => 'image/jpeg'],
    )]);

    expect(resolve(DownloadCover::class)->handle(12054527))->toBeNull();
});

it('returns null rather than throwing when the transport fails', function (): void {
    Http::fake(fn () => throw new ConnectionException('network down'));

    expect(resolve(DownloadCover::class)->handle(12054527))->toBeNull();
});

it('gives each download its own filename', function (): void {
    Http::fake(['covers.openlibrary.org/*' => Http::response(
        File::image('cover.jpg')->getContent(), 200, ['Content-Type' => 'image/jpeg'],
    )]);

    $first = resolve(DownloadCover::class)->handle(12054527);
    $second = resolve(DownloadCover::class)->handle(12054527);

    expect($first)->not->toBe($second)
        ->and(Storage::disk('public')->allFiles())->toHaveCount(2);
});

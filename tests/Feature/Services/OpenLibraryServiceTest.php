<?php

declare(strict_types=1);

use App\Contracts\ResolvesIsbn;
use App\Support\Isbn;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function testIsbn(): Isbn
{
    $isbn = Isbn::tryFrom('9780451524935');

    expect($isbn)->not->toBeNull();

    return $isbn;
}

/**
 * @param  array<string, mixed>  $edition
 * @param  array<int, array<string, mixed>>  $searchDocs
 */
function fakeOpenLibrary(array $edition = [], array $searchDocs = [['author_name' => ['George Orwell']]]): void
{
    Http::fake([
        'openlibrary.org/isbn/*' => Http::response([...[
            'title' => 'Nineteen Eighty-Four',
            'publishers' => ['Signet Classics'],
            'publish_date' => '1993?',
            'publish_places' => ['New York'],
            'number_of_pages' => 328,
            'covers' => [12054527],
        ], ...$edition]),
        'openlibrary.org/search.json*' => Http::response(['docs' => $searchDocs]),
    ]);
}

beforeEach(function (): void {
    Cache::flush();
    config(['services.open_library.contact' => 'test@example.test']);
});

it('maps an edition record onto the metadata shape', function (): void {
    fakeOpenLibrary();

    $metadata = resolve(ResolvesIsbn::class)->resolve(testIsbn());

    expect($metadata?->title)->toBe('Nineteen Eighty-Four')
        ->and($metadata?->publisherName)->toBe('Signet Classics')
        ->and($metadata?->publicationYear)->toBe(1993)
        ->and($metadata?->publicationLocation)->toBe('New York')
        ->and($metadata?->pageCount)->toBe(328)
        ->and($metadata?->coverId)->toBe(12054527);
});

it('takes the author name from the search endpoint, not the edition', function (): void {
    fakeOpenLibrary();

    expect(resolve(ResolvesIsbn::class)->resolve(testIsbn())?->authorName)->toBe('George Orwell');
});

it('identifies itself so Open Library raises the rate limit', function (): void {
    fakeOpenLibrary();

    resolve(ResolvesIsbn::class)->resolve(testIsbn());

    Http::assertSent(fn ($request): bool => str_contains(
        $request->header('User-Agent')[0] ?? '', 'test@example.test'
    ));
});

it('returns null when the edition is not found', function (): void {
    Http::fake(['openlibrary.org/*' => Http::response([], 404)]);

    expect(resolve(ResolvesIsbn::class)->resolve(testIsbn()))->toBeNull();
});

it('returns null when the record has no title, which it cannot use', function (): void {
    Http::fake([
        'openlibrary.org/isbn/*' => Http::response(['publishers' => ['Signet Classics']]),
        'openlibrary.org/search.json*' => Http::response(['docs' => []]),
    ]);

    expect(resolve(ResolvesIsbn::class)->resolve(testIsbn()))->toBeNull();
});

it('returns null rather than throwing when the transport fails', function (): void {
    Http::fake(fn () => throw new ConnectionException('network down'));

    expect(resolve(ResolvesIsbn::class)->resolve(testIsbn()))->toBeNull();
});

it('survives a record missing every optional field', function (): void {
    Http::fake([
        'openlibrary.org/isbn/*' => Http::response(['title' => 'Bare Record']),
        'openlibrary.org/search.json*' => Http::response(['docs' => []]),
    ]);

    $metadata = resolve(ResolvesIsbn::class)->resolve(testIsbn());

    expect($metadata?->title)->toBe('Bare Record')
        ->and($metadata?->authorName)->toBeNull()
        ->and($metadata?->pageCount)->toBeNull()
        ->and($metadata?->coverId)->toBeNull();
});

it('parses the year out of free text, and refuses to guess', function (string $publishDate, ?int $expected): void {
    fakeOpenLibrary(['publish_date' => $publishDate]);

    expect(resolve(ResolvesIsbn::class)->resolve(testIsbn())?->publicationYear)->toBe($expected);
})->with([
    'plain year' => ['1993', 1993],
    'uncertain year' => ['1993?', 1993],
    'circa' => ['c1993', 1993],
    'month and year' => ['March 1993', 1993],
    'iso month' => ['1993-06', 1993],
    'no date' => ['n.d.', null],
    'empty' => ['', null],
    'not a year' => ['12345', null],
    'implausibly early' => ['0993', null],
]);

it('serves a second lookup from cache instead of asking again', function (): void {
    fakeOpenLibrary();

    resolve(ResolvesIsbn::class)->resolve(testIsbn());
    resolve(ResolvesIsbn::class)->resolve(testIsbn());

    Http::assertSentCount(2);
});

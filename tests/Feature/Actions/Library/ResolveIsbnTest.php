<?php

declare(strict_types=1);

use App\Actions\Library\ResolveIsbn;
use App\Contracts\ResolvesIsbn;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\Writer;
use App\Support\BookMetadata;
use App\Support\Isbn;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

function anIsbn(string $value = '9780451524935'): Isbn
{
    $isbn = Isbn::tryFrom($value);

    expect($isbn)->not->toBeNull();

    return $isbn;
}

function fakeResolver(?BookMetadata $metadata): void
{
    $stub = Mockery::mock(ResolvesIsbn::class);
    $stub->shouldReceive('resolve')->andReturn($metadata);

    app()->instance(ResolvesIsbn::class, $stub);
}

function orwell(): BookMetadata
{
    return new BookMetadata(
        title: 'Nineteen Eighty-Four',
        authorName: 'George Orwell',
        publisherName: 'Signet Classics',
        publicationYear: 1993,
        publicationLocation: 'New York',
        pageCount: 328,
    );
}

beforeEach(function (): void {
    Storage::fake('public');
    config(['services.open_library.contact' => 'test@example.test']);
});

it('reports the fields the form can fill', function (): void {
    fakeResolver(orwell());

    $result = resolve(ResolveIsbn::class)->handle(anIsbn());

    expect($result['values'])->toBe([
        'name' => 'Nineteen Eighty-Four',
        'page_count' => 328,
        'publication_date' => 1993,
        'publication_location' => 'New York',
        'isbn' => '9780451524935',
    ]);
});

it('selects a writer that already exists', function (): void {
    $writer = Writer::factory()->create(['name' => 'George Orwell']);
    fakeResolver(orwell());

    expect(resolve(ResolveIsbn::class)->handle(anIsbn())['writer'])
        ->toBe(['id' => $writer->id, 'name' => 'George Orwell']);
});

it('matches a writer regardless of case', function (): void {
    $writer = Writer::factory()->create(['name' => 'george orwell']);
    fakeResolver(orwell());

    expect(resolve(ResolveIsbn::class)->handle(anIsbn())['writer']['id'])->toBe($writer->id);
});

it('suggests a writer that does not exist rather than creating one', function (): void {
    fakeResolver(orwell());

    expect(resolve(ResolveIsbn::class)->handle(anIsbn())['writer'])
        ->toBe(['suggestion' => 'George Orwell'])
        ->and(Writer::count())->toBe(0);
});

it('selects a publisher that already exists', function (): void {
    $publisher = Publisher::factory()->create(['name' => 'Signet Classics']);
    fakeResolver(orwell());

    expect(resolve(ResolveIsbn::class)->handle(anIsbn())['publisher'])
        ->toBe(['id' => $publisher->id, 'name' => 'Signet Classics']);
});

it('reports no writer at all when the record names none', function (): void {
    fakeResolver(new BookMetadata(title: 'Anonymous Work'));

    expect(resolve(ResolveIsbn::class)->handle(anIsbn())['writer'])->toBeNull();
});

it('reports a duplicate and fills nothing when the isbn is already in the library', function (): void {
    $existing = Book::factory()->create(['isbn' => '9780451524935', 'name' => 'Already Here']);
    fakeResolver(orwell());

    $result = resolve(ResolveIsbn::class)->handle(anIsbn());

    expect($result['duplicate'])->toBe(['id' => $existing->id, 'name' => 'Already Here'])
        ->and($result['values'])->toBeEmpty();
});

it('returns an empty result when the resolver finds nothing', function (): void {
    fakeResolver(null);

    $result = resolve(ResolveIsbn::class)->handle(anIsbn());

    expect($result['values'])->toBeEmpty()
        ->and($result['writer'])->toBeNull()
        ->and($result['duplicate'])->toBeNull();
});

it('omits fields the record does not carry', function (): void {
    fakeResolver(new BookMetadata(title: 'Bare Record'));

    expect(resolve(ResolveIsbn::class)->handle(anIsbn())['values'])
        ->toBe(['name' => 'Bare Record', 'isbn' => '9780451524935']);
});

it('downloads a cover when the record has one', function (): void {
    fakeResolver(new BookMetadata(title: 'With Cover', coverId: 12054527));

    Http::fake(['covers.openlibrary.org/*' => Http::response(
        File::image('cover.jpg')->getContent(), 200, ['Content-Type' => 'image/jpeg'],
    )]);

    expect(resolve(ResolveIsbn::class)->handle(anIsbn())['image'])->toStartWith('books/');
});

it('succeeds without an image when the cover cannot be fetched', function (): void {
    fakeResolver(new BookMetadata(title: 'No Cover', coverId: 999_999_999));

    Http::fake(['covers.openlibrary.org/*' => Http::response('', 404)]);

    $result = resolve(ResolveIsbn::class)->handle(anIsbn());

    expect($result['image'])->toBeNull()
        ->and($result['values']['name'])->toBe('No Cover');
});

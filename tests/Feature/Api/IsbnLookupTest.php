<?php

declare(strict_types=1);

use App\Models\Book;
use App\Models\Publisher;
use App\Models\User;
use App\Models\Writer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    config(['app.admin_email' => 'owner@example.test']);
    $this->owner = User::factory()->admin()->create(['email' => 'owner@example.test']);
    $this->member = User::factory()->member()->create(['email' => 'her@example.test']);
});

function fakeIsbnEdition(array $edition = [], ?string $author = 'Sabahattin Ali'): void
{
    Http::fake([
        'openlibrary.org/isbn/*' => Http::response($edition + [
            'title' => 'Kurk Mantolu Madonna',
            'publishers' => ['Yap Kredi Yaynlar'],
            'publish_date' => '2016',
            'publish_places' => ['Istanbul'],
            'number_of_pages' => 164,
        ]),
        'openlibrary.org/search.json*' => Http::response([
            'docs' => $author === null ? [] : [['author_name' => [$author]]],
        ]),
        'covers.openlibrary.org/*' => Http::response('binary-jpeg-body', 200, ['Content-Type' => 'image/jpeg']),
    ]);
}

function isbnLookup(User $user, string $isbn): TestResponse
{
    return apiAs($user)->post(route('api.v1.books.isbn'), ['isbn' => $isbn]);
}

it('turns away a request with no token', function (): void {
    $this->postJson(route('api.v1.books.isbn'), ['isbn' => '9789753638029'])->assertUnauthorized();
});

/**
 * Areas follow the role, and both roles hold the library, so the only account
 * without it is one whose role the enum does not recognise.
 */
it('is a 404 for an account with no library area', function (): void {
    fakeIsbnEdition();

    isbnLookup(userWithoutRole('nobody@example.test'), '9789753638029')->assertNotFound();
});

it('answers a member, who holds the library area', function (): void {
    fakeIsbnEdition();

    isbnLookup($this->member, '9789753638029')->assertOk();
});

it('rejects an ISBN that fails its checksum', function (): void {
    isbnLookup($this->owner, '9789753638020')
        ->assertStatus(422)
        ->assertJsonPath('errors.isbn.0', 'That is not a valid ISBN. Check the digits and try again.');

    Http::assertNothingSent();
});

it('rejects an ISBN that is not digits at all', function (): void {
    isbnLookup($this->owner, 'not-a-book')->assertStatus(422);
});

it('requires an isbn', function (): void {
    apiAs($this->owner)->post(route('api.v1.books.isbn'))->assertStatus(422);
});

it('returns the fields a book form needs', function (): void {
    fakeIsbnEdition();

    $data = isbnLookup($this->owner, '9789753638029')->assertOk()->json('data');

    expect($data['values']['name'])->toBe('Kurk Mantolu Madonna')
        ->and($data['values']['isbn'])->toBe('9789753638029')
        ->and($data['values']['page_count'])->toBe(164)
        ->and($data['values']['publication_date'])->toBe(2016)
        ->and($data['values']['publication_location'])->toBe('Istanbul')
        ->and($data['duplicate'])->toBeNull();
});

/**
 * A ten digit ISBN and its thirteen digit form are the same book, so the
 * response carries the normalised value whichever one was scanned or typed.
 */
it('normalises a ten digit ISBN to thirteen', function (): void {
    fakeIsbnEdition();

    $data = isbnLookup($this->owner, '975-363-802-7')->assertOk()->json('data');

    expect($data['values']['isbn'])->toBe('9789753638029');
});

it('names the book already on the shelf rather than offering a second copy', function (): void {
    fakeIsbnEdition();

    $existing = Book::factory()->create(['isbn' => '9789753638029', 'name' => 'Kürk Mantolu Madonna']);

    $data = isbnLookup($this->owner, '9789753638029')->assertOk()->json('data');

    expect($data['duplicate'])->toBe(['id' => $existing->id, 'name' => 'Kürk Mantolu Madonna'])
        ->and($data['values'])->toBe([]);
});

it('matches a writer and publisher already known by name', function (): void {
    fakeIsbnEdition();

    $writer = Writer::factory()->create(['name' => 'Sabahattin Ali']);
    $publisher = Publisher::factory()->create(['name' => 'Yap Kredi Yaynlar']);

    $data = isbnLookup($this->owner, '9789753638029')->assertOk()->json('data');

    expect($data['writer'])->toBe(['id' => $writer->id, 'name' => 'Sabahattin Ali'])
        ->and($data['publisher'])->toBe(['id' => $publisher->id, 'name' => 'Yap Kredi Yaynlar']);
});

/**
 * An unmatched name comes back as a suggestion and nothing is created. Open
 * Library stores Turkish titles with the diacritics stripped, so creating a
 * writer from one would park "Yap Kredi Yaynlar" beside the real record.
 */
it('suggests an unknown writer rather than creating one', function (): void {
    fakeIsbnEdition();

    $data = isbnLookup($this->owner, '9789753638029')->assertOk()->json('data');

    expect($data['writer'])->toBe(['suggestion' => 'Sabahattin Ali'])
        ->and($data['publisher'])->toBe(['suggestion' => 'Yap Kredi Yaynlar'])
        ->and(Writer::query()->count())->toBe(0)
        ->and(Publisher::query()->count())->toBe(0);
});

it('reports no writer when the search knows of none', function (): void {
    fakeIsbnEdition(author: null);

    expect(isbnLookup($this->owner, '9789753638029')->assertOk()->json('data.writer'))->toBeNull();
});

/**
 * The panel reads the cover off the public disk by path. A phone cannot, so the
 * API hands back something it can actually fetch.
 */
it('returns the cover as a URL rather than a disk path', function (): void {
    Storage::fake('public');
    fakeIsbnEdition(['covers' => [10031512]]);

    $image = isbnLookup($this->owner, '9789753638029')->assertOk()->json('data.image');

    expect($image)->toStartWith('http')
        ->and($image)->toContain('/books/')
        ->and($image)->toEndWith('.jpg');
});

it('returns no image when the edition has no cover', function (): void {
    fakeIsbnEdition();

    expect(isbnLookup($this->owner, '9789753638029')->assertOk()->json('data.image'))->toBeNull();
});

it('comes back empty when Open Library has never heard of the book', function (): void {
    Http::fake(['openlibrary.org/*' => Http::response([], 404)]);

    $data = isbnLookup($this->owner, '9789753638029')->assertOk()->json('data');

    expect($data['values'])->toBe([])
        ->and($data['writer'])->toBeNull()
        ->and($data['publisher'])->toBeNull()
        ->and($data['duplicate'])->toBeNull();
});

/**
 * books/{book} would swallow books/isbn if it were registered first, which is
 * the same trap the schema routes fell into.
 */
it('registers the lookup ahead of the book parameter route', function (): void {
    $uris = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (str_starts_with($route->uri(), 'api/v1/books')) {
            $uris[] = $route->uri();
        }
    }

    $lookup = array_search('api/v1/books/isbn', $uris, true);
    $parameter = array_search('api/v1/books/{book}', $uris, true);

    expect($lookup)->not->toBeFalse()
        ->and($parameter)->not->toBeFalse()
        ->and($lookup)->toBeLessThan($parameter);
});

it('throttles the lookup, because every miss calls out to Open Library', function (): void {
    $this->freezeTime();

    fakeIsbnEdition();

    foreach (range(1, 20) as $ignored) {
        isbnLookup($this->owner, '9789753638029')->assertOk();
    }

    isbnLookup($this->owner, '9789753638029')->assertStatus(429);
});

it('sends values as a JSON object even when it holds nothing', function (): void {
    Http::fake(['openlibrary.org/*' => Http::response([], 404)]);

    $body = isbnLookup($this->owner, '9789753638029')->assertOk()->getContent();

    expect($body)->toContain('"values":{}')
        ->and($body)->not->toContain('"values":[]');
});

it('sends values as a JSON object for a book already on the shelf', function (): void {
    fakeIsbnEdition();

    Book::factory()->create(['isbn' => '9789753638029', 'name' => 'Kürk Mantolu Madonna']);

    $body = isbnLookup($this->owner, '9789753638029')->assertOk()->getContent();

    expect($body)->toContain('"values":{}')
        ->and($body)->not->toContain('"values":[]');
});

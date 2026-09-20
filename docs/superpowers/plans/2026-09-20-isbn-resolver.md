# ISBN Resolver Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Paste an ISBN on the book form, press Fetch, and have title, publisher, year, place, page count and cover filled in from Open Library, with nothing written until you save.

**Architecture:** A pure `Isbn` value object rejects bad input before any request. One service class owns every detail of talking to Open Library, behind a contract so it stays final and substitutable. Three actions orchestrate: resolve, create a missing writer or publisher, download a cover. A new server-driven field type puts the Fetch button on both Create and Edit without touching either page.

**Tech Stack:** PHP 8.5, Laravel 13, Pest 5, Vue 3 + PrimeVue, Open Library API (free, keyless).

**Spec:** `docs/superpowers/specs/2026-09-20-isbn-resolver-design.md`

## Global Constraints

- **Work directly on `master`.** No feature branches.
- **Commit messages carry no AI attribution trailer.**
- **Conventional Commits:** `feat:`, `fix:`, `chore:`, `refactor:`, `test:`, `docs:`.
- **Every class is `final`** except the five documented abstract bases. `tests/Feature/ArchTest.php` enforces this.
- **Every action lives at `App\Actions\<Domain>\<VerbNoun>`, is final, and exposes one `handle()`.**
- **PHPStan runs at `level: max` with `--memory-limit=1G`.** No baseline, no `ignoreErrors`.
- **Type coverage stays at 100%** — every parameter, return and class constant typed.
- **Never issue a bulk write through the query builder.** `tests/Feature/Actions/Resources/BulkWriteSafetyTest.php` fails the build if you do.
- **Tests must not depend on the local `.env`.** Anything read via `config()` is set in the test itself.
- **Gates before every push:** `composer ci:check`, `npm run lint:check`, `npm run format:check`.
- **Open Library requests always send** `User-Agent: myblog/1.0 (<contact>)` and cover requests always append `?default=false`.

---

## File Structure

**Created:**

| Path | Responsibility |
| --- | --- |
| `database/migrations/*_add_isbn_to_books_table.php` | Nullable unique `isbn` column |
| `app/Support/Isbn.php` | Value object: normalise, checksum, 10→13 |
| `app/Support/BookMetadata.php` | Readonly DTO of one resolved edition |
| `app/Contracts/ResolvesIsbn.php` | `resolve(Isbn): ?BookMetadata` |
| `app/Services/OpenLibraryService.php` | The only class that knows the endpoints |
| `app/Actions/Library/ResolveIsbn.php` | Validate, fetch, match, report |
| `app/Actions/Library/FindOrCreateNamedRecord.php` | Backs the Create button |
| `app/Actions/Library/DownloadCover.php` | Fetch and store a cover |
| `app/Http/Controllers/Admin/Library/IsbnLookupController.php` | Two endpoints |
| `tests/Unit/IsbnTest.php` | Checksum and normalisation datasets |
| `tests/Feature/Services/OpenLibraryServiceTest.php` | `Http::fake()` behaviour |
| `tests/Feature/Actions/Library/ResolveIsbnTest.php` | Matching and duplicates |
| `tests/Feature/Actions/Library/FindOrCreateNamedRecordTest.php` | Create or reuse |
| `tests/Feature/Actions/Library/DownloadCoverTest.php` | Storage and rejections |
| `tests/Feature/Admin/Library/IsbnLookupTest.php` | Endpoint contract |

**Modified:** `config/services.php`, `.env.example`, `app/Forms/Field.php`, `app/Forms/Definitions/BookForm.php`, `app/Http/Requests/Admin/BookRequest.php`, `app/Models/Book.php`, `database/factories/BookFactory.php`, `routes/admin.php`, `resources/js/Components/Form/FormField.vue`, `tests/Browser/AdminSmokeTest.php`, `CHANGELOG.md`.

---

### Task 1: The `Isbn` value object

**Files:**
- Create: `app/Support/Isbn.php`
- Test: `tests/Unit/IsbnTest.php`

**Interfaces:**
- Produces: `Isbn::tryFrom(string $input): ?self`, `$isbn->value(): string` (13 digits, no hyphens).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/IsbnTest.php`:

```php
<?php

declare(strict_types=1);

use App\Support\Isbn;

it('accepts a well formed ISBN and normalises it to thirteen digits', function (string $input, string $expected): void {
    expect(Isbn::tryFrom($input)?->value())->toBe($expected);
})->with([
    'plain isbn-13' => ['9780451524935', '9780451524935'],
    'hyphenated isbn-13' => ['978-0-451-52493-5', '9780451524935'],
    'spaced isbn-13' => ['978 0 451 52493 5', '9780451524935'],
    'isbn-10 converts to 13' => ['0451524934', '9780451524935'],
    'hyphenated isbn-10' => ['0-451-52493-4', '9780451524935'],
    'isbn-10 with an X check digit' => ['043942089X', '9780439420891'],
    'surrounding whitespace' => ['  9780451524935  ', '9780451524935'],
]);

it('rejects anything that is not a valid ISBN', function (string $input): void {
    expect(Isbn::tryFrom($input))->toBeNull();
})->with([
    'empty' => [''],
    'whitespace only' => ['   '],
    'too short' => ['978045152493'],
    'too long' => ['97804515249355'],
    'letters' => ['not-an-isbn-at'],
    'bad isbn-13 check digit' => ['9780451524936'],
    'bad isbn-10 check digit' => ['0451524935'],
    'transposed digits break the checksum' => ['9780451529435'],
    'X in the wrong position' => ['04X9420891'],
    'thirteen digits with a bad prefix' => ['1230451524935'],
]);

it('treats the two forms of the same book as equal', function (): void {
    expect(Isbn::tryFrom('0451524934')?->value())->toBe(Isbn::tryFrom('9780451524935')?->value());
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=IsbnTest`
Expected: FAIL with `Class "App\Support\Isbn" not found`.

- [ ] **Step 3: Write the value object**

Create `app/Support/Isbn.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

/**
 * A validated ISBN, held in its thirteen digit form.
 *
 * Construction verifies the check digit, so a typo is rejected here rather
 * than spending a request to find out Open Library has never heard of it.
 */
final readonly class Isbn
{
    private function __construct(public string $digits) {}

    public static function tryFrom(string $input): ?self
    {
        $normalised = strtoupper(preg_replace('/[\s-]+/', '', trim($input)) ?? '');

        if (preg_match('/^\d{9}[\dX]$/', $normalised) === 1) {
            return self::isValidTen($normalised) ? new self(self::tenToThirteen($normalised)) : null;
        }

        if (preg_match('/^(978|979)\d{10}$/', $normalised) === 1) {
            return self::isValidThirteen($normalised) ? new self($normalised) : null;
        }

        return null;
    }

    public function value(): string
    {
        return $this->digits;
    }

    private static function isValidTen(string $digits): bool
    {
        $sum = 0;

        for ($i = 0; $i < 10; $i++) {
            $char = $digits[$i];
            $value = $char === 'X' ? 10 : (int) $char;
            $sum += $value * (10 - $i);
        }

        return $sum % 11 === 0;
    }

    private static function isValidThirteen(string $digits): bool
    {
        $sum = 0;

        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $digits[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $sum % 10 === 0;
    }

    private static function tenToThirteen(string $digits): string
    {
        $body = '978'.substr($digits, 0, 9);
        $sum = 0;

        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $body[$i] * ($i % 2 === 0 ? 1 : 3);
        }

        return $body.((10 - $sum % 10) % 10);
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=IsbnTest`
Expected: PASS, 18 assertions.

If `'X in the wrong position' => ['04X9420891']` passes when it should fail, the
regex is wrong: `X` is only ever the tenth character.

- [ ] **Step 5: Verify the gates**

Run: `composer lint && composer types:check && composer type-coverage`
Expected: all clean.

- [ ] **Step 6: Commit**

```bash
git add app/Support/Isbn.php tests/Unit/IsbnTest.php
git commit -m "feat: add a validated ISBN value object" -- app/Support/Isbn.php tests/Unit/IsbnTest.php
```

---

### Task 2: The `isbn` column

**Files:**
- Create: `database/migrations/*_add_isbn_to_books_table.php`
- Modify: `app/Http/Requests/Admin/BookRequest.php`, `database/factories/BookFactory.php`
- Test: `tests/Feature/Admin/Library/LibraryResourcesTest.php`

**Interfaces:**
- Produces: `books.isbn`, nullable, unique, `varchar(13)`.

- [ ] **Step 1: Create the migration**

Run: `php artisan make:migration add_isbn_to_books_table --no-interaction`

Replace its body:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unique so the lookup can say "you already have this book" rather than
 * letting a duplicate be created. Nullable because the books already in the
 * library were entered by hand and have none.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->string('isbn', 13)->nullable()->unique()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table): void {
            $table->dropUnique('books_isbn_unique');
            $table->dropColumn('isbn');
        });
    }
};
```

- [ ] **Step 2: Apply it and verify the round trip**

```bash
php artisan migrate --no-interaction
php artisan migrate:rollback --step=1 --no-interaction
php artisan migrate --no-interaction
```

Expected: all three succeed. A failing rollback means the unique index name is
wrong; check it with `SHOW INDEX FROM books`.

- [ ] **Step 3: Add the validation rule**

In `app/Http/Requests/Admin/BookRequest.php`, inside `rules()`, after the `slug` line:

```php
'isbn' => ['nullable', 'string', 'size:13', Rule::unique('books', 'isbn')->ignore($bookId)],
```

- [ ] **Step 4: Keep the factory explicit**

In `database/factories/BookFactory.php`, add to `definition()`:

```php
'isbn' => null,
```

Books in tests have no ISBN unless a test says so. Generating a random one would
collide with the unique index.

- [ ] **Step 5: Write the failing test**

Append to `tests/Feature/Admin/Library/LibraryResourcesTest.php`:

```php
it('stores an isbn on a book', function (): void {
    $book = Book::factory()->create(['isbn' => '9780451524935']);

    expect($book->fresh()->isbn)->toBe('9780451524935');
});

it('refuses a second book with the same isbn', function (): void {
    Book::factory()->create(['isbn' => '9780451524935']);

    expect(fn () => Book::factory()->create(['isbn' => '9780451524935']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('allows many books with no isbn', function (): void {
    Book::factory()->count(3)->create(['isbn' => null]);

    expect(Book::whereNull('isbn')->count())->toBe(3);
});
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=LibraryResources`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/ app/Http/Requests/Admin/BookRequest.php tests/
git commit -m "feat: add a unique isbn column to books" -- database/ app/Http/Requests/Admin/BookRequest.php tests/
```

---

### Task 3: `BookMetadata` and the `ResolvesIsbn` contract

**Files:**
- Create: `app/Support/BookMetadata.php`, `app/Contracts/ResolvesIsbn.php`

**Interfaces:**
- Consumes: `App\Support\Isbn` from Task 1.
- Produces: `BookMetadata` with public readonly `title`, `authorName`, `publisherName`, `publicationYear`, `publicationLocation`, `pageCount`, `coverId`; and `ResolvesIsbn::resolve(Isbn): ?BookMetadata`.

- [ ] **Step 1: Write the DTO**

Create `app/Support/BookMetadata.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

/**
 * One resolved edition.
 *
 * Only the title is required: an edition record without one cannot fill the
 * single field the book form demands, so the service treats it as no record at
 * all. Everything else is absent often enough in Open Library to be nullable.
 */
final readonly class BookMetadata
{
    public function __construct(
        public string $title,
        public ?string $authorName = null,
        public ?string $publisherName = null,
        public ?int $publicationYear = null,
        public ?string $publicationLocation = null,
        public ?int $pageCount = null,
        public ?int $coverId = null,
    ) {}
}
```

- [ ] **Step 2: Write the contract**

Create `app/Contracts/ResolvesIsbn.php`:

```php
<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\BookMetadata;
use App\Support\Isbn;

/**
 * Looks up one edition by ISBN.
 *
 * Returns null when no record exists or the source cannot be reached: a
 * lookup that finds nothing is an ordinary outcome, not an exception.
 */
interface ResolvesIsbn
{
    public function resolve(Isbn $isbn): ?BookMetadata;
}
```

- [ ] **Step 3: Verify the gates**

Run: `composer lint && composer types:check`
Expected: clean. `ArchTest` is not affected yet — `App\Contracts` is already
allowed to reference other namespaces.

- [ ] **Step 4: Commit**

```bash
git add app/Support/BookMetadata.php app/Contracts/ResolvesIsbn.php
git commit -m "feat: add the ISBN resolution contract and its metadata shape" -- app/Support/BookMetadata.php app/Contracts/ResolvesIsbn.php
```

---

### Task 4: `OpenLibraryService`

**Files:**
- Create: `app/Services/OpenLibraryService.php`
- Modify: `config/services.php`, `.env.example`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Services/OpenLibraryServiceTest.php`

**Interfaces:**
- Consumes: `Isbn`, `BookMetadata`, `ResolvesIsbn` from Tasks 1 and 3.
- Produces: `OpenLibraryService implements ResolvesIsbn`, bound to the contract in `AppServiceProvider::register()`.

**Why two requests.** The edition record at `/isbn/{isbn}.json` is the only
source of facts about this ISBN, but carries no dependable author name.
`search.json` resolves the author, but its `publisher`, `publish_place` and
`first_publish_year` are aggregates across every edition of the work and would
fill the form with the wrong publisher. Verified on 2026-09-20; see the spec.

- [ ] **Step 1: Add the config**

In `config/services.php`, before the closing `];`:

```php
'open_library' => [
    // Identifying the caller raises Open Library's limit from 1 to 3 requests
    // a second. It is a courtesy, not a credential.
    'contact' => env('OPEN_LIBRARY_CONTACT', env('ADMIN_EMAIL', '')),
],
```

In `.env.example`, after the `ADMIN_NAME` block:

```
# Optional. Sent in the User-Agent to Open Library; defaults to ADMIN_EMAIL.
OPEN_LIBRARY_CONTACT=
```

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/Services/OpenLibraryServiceTest.php`:

```php
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

function fakeOpenLibrary(array $edition = [], array $searchDocs = [[ 'author_name' => ['George Orwell']]]): void
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
```

The last test is the load-bearing one: two resolves must produce the two
requests of a single lookup, not four.

- [ ] **Step 3: Run it and watch it fail**

Run: `php artisan test --filter=OpenLibraryService`
Expected: FAIL — nothing is bound to `ResolvesIsbn`.

- [ ] **Step 4: Write the service**

Create `app/Services/OpenLibraryService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ResolvesIsbn;
use App\Support\BookMetadata;
use App\Support\Isbn;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The only class that knows how to talk to Open Library.
 *
 * Two requests per lookup, deliberately. The edition record holds the facts
 * about this ISBN but no dependable author name; the search endpoint resolves
 * the author but reports publisher and year aggregated across every edition of
 * the work. Taking each from the wrong place fills the form with the wrong book.
 */
final class OpenLibraryService implements ResolvesIsbn
{
    private const string EDITION_URL = 'https://openlibrary.org/isbn/';

    private const string SEARCH_URL = 'https://openlibrary.org/search.json';

    private const int CACHE_DAYS = 30;

    public function resolve(Isbn $isbn): ?BookMetadata
    {
        /** @var array<string, mixed>|null $payload */
        $payload = Cache::remember(
            'open-library:'.$isbn->value(),
            now()->addDays(self::CACHE_DAYS),
            fn (): ?array => $this->fetch($isbn),
        );

        if ($payload === null) {
            return null;
        }

        $title = $payload['title'] ?? null;

        if (! is_string($title) || trim($title) === '') {
            return null;
        }

        return new BookMetadata(
            title: $title,
            authorName: $this->stringOrNull($payload['author'] ?? null),
            publisherName: $this->stringOrNull($payload['publisher'] ?? null),
            publicationYear: $this->year($payload['publish_date'] ?? null),
            publicationLocation: $this->stringOrNull($payload['publish_place'] ?? null),
            pageCount: is_int($payload['pages'] ?? null) ? $payload['pages'] : null,
            coverId: is_int($payload['cover'] ?? null) ? $payload['cover'] : null,
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetch(Isbn $isbn): ?array
    {
        try {
            $edition = $this->client()->get(self::EDITION_URL.$isbn->value().'.json');

            if (! $edition->successful()) {
                return null;
            }

            /** @var array<string, mixed> $record */
            $record = $edition->json();

            return [
                'title' => $record['title'] ?? null,
                'publisher' => $this->firstOf($record['publishers'] ?? null),
                'publish_date' => $record['publish_date'] ?? null,
                'publish_place' => $this->firstOf($record['publish_places'] ?? null),
                'pages' => $record['number_of_pages'] ?? null,
                'cover' => $this->firstOf($record['covers'] ?? null),
                'author' => $this->author($isbn),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The edition record does not reliably carry an author name; the search
     * endpoint resolves it inline, and nothing else it returns is used.
     */
    private function author(Isbn $isbn): ?string
    {
        $response = $this->client()->get(self::SEARCH_URL, [
            'q' => 'isbn:'.$isbn->value(),
            'limit' => 1,
            'fields' => 'author_name',
        ]);

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed> $body */
        $body = $response->json();
        $docs = $body['docs'] ?? [];

        if (! is_array($docs) || $docs === []) {
            return null;
        }

        $first = $docs[0] ?? null;

        return is_array($first) ? $this->firstOf($first['author_name'] ?? null) : null;
    }

    private function client(): PendingRequest
    {
        $contact = config()->string('services.open_library.contact');

        return Http::withUserAgent('myblog/1.0 ('.$contact.')')
            ->timeout(5)
            ->retry(2, 200)
            ->withOptions(['allow_redirects' => true]);
    }

    private function firstOf(mixed $value): mixed
    {
        return is_array($value) ? ($value[0] ?? null) : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * publish_date is free text: "1993", "1993?", "c1993", "March 1993".
     * Take the first plausible four digit year and otherwise give up, because
     * books.publication_date is a YEAR column and a blank beats a wrong guess.
     */
    private function year(mixed $value): ?int
    {
        if (! is_string($value)) {
            return null;
        }

        if (preg_match('/\b(1\d{3}|20\d{2})\b/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];

        return $year >= 1000 && $year <= (int) date('Y') ? $year : null;
    }
}
```

- [ ] **Step 5: Bind it**

In `app/Providers/AppServiceProvider.php`, add the import and one binding inside `register()`:

```php
use App\Contracts\ResolvesIsbn;
use App\Services\OpenLibraryService;
```

```php
$this->app->bind(ResolvesIsbn::class, OpenLibraryService::class);
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter=OpenLibraryService`
Expected: PASS.

If the cache test reports 4 requests, `Cache::remember` is caching the
`BookMetadata` rather than the raw payload, or the key is not the ISBN.

- [ ] **Step 7: Verify the gates**

Run: `composer lint && composer types:check && composer type-coverage`

- [ ] **Step 8: Commit**

```bash
git add app/ config/services.php .env.example tests/
git commit -m "feat: resolve an ISBN against Open Library" -- app/ config/services.php .env.example tests/
```

---

### Task 5: `DownloadCover`

**Files:**
- Create: `app/Actions/Library/DownloadCover.php`
- Test: `tests/Feature/Actions/Library/DownloadCoverTest.php`

**Interfaces:**
- Produces: `DownloadCover::handle(int $coverId): ?string` — the stored path on the `public` disk, or `null`.

**The whole point of this task.** `covers.openlibrary.org/b/id/{id}-L.jpg`
returns **HTTP 200 with a 43 byte placeholder** when the cover does not exist.
Appending `?default=false` makes it 404 instead. Without that parameter the
application silently stores blank images. Verified on 2026-09-20.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Actions/Library/DownloadCoverTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Library\DownloadCover;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
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
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=DownloadCover`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the action**

Create `app/Actions/Library/DownloadCover.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Library;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fetch a cover from Open Library and store it like a manual upload.
 *
 * default=false is not optional: without it a missing cover comes back as
 * HTTP 200 carrying a blank placeholder, and the library fills up with empty
 * images that look like successful downloads.
 */
final class DownloadCover
{
    private const string BASE_URL = 'https://covers.openlibrary.org/b/id/';

    private const int MAX_BYTES = 5 * 1024 * 1024;

    /**
     * @return string|null the stored path on the public disk
     */
    public function handle(int $coverId): ?string
    {
        try {
            $response = Http::withUserAgent('myblog/1.0 ('.config()->string('services.open_library.contact').')')
                ->timeout(10)
                ->withOptions(['allow_redirects' => true])
                ->get(self::BASE_URL.$coverId.'-L.jpg', ['default' => 'false']);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        if (! str_starts_with($response->header('Content-Type'), 'image/')) {
            return null;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        $path = 'books/'.Str::uuid()->toString().'.jpg';

        Storage::disk('public')->put($path, $body);

        return $path;
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=DownloadCover`
Expected: PASS, 7 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Library/DownloadCover.php tests/Feature/Actions/Library/DownloadCoverTest.php
git commit -m "feat: download an Open Library cover into storage" -- app/Actions/Library/DownloadCover.php tests/Feature/Actions/Library/DownloadCoverTest.php
```

---

### Task 6: `FindOrCreateNamedRecord`

**Files:**
- Create: `app/Actions/Library/FindOrCreateNamedRecord.php`
- Test: `tests/Feature/Actions/Library/FindOrCreateNamedRecordTest.php`

**Interfaces:**
- Produces: `FindOrCreateNamedRecord::handle(string $modelClass, string $name): Model`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Actions/Library/FindOrCreateNamedRecordTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Library\FindOrCreateNamedRecord;
use App\Models\Publisher;
use App\Models\Writer;

it('returns the existing record when the name already exists', function (): void {
    $writer = Writer::factory()->create(['name' => 'George Orwell']);

    $found = resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, 'George Orwell');

    expect($found->getKey())->toBe($writer->getKey())
        ->and(Writer::count())->toBe(1);
});

it('matches regardless of case', function (): void {
    $writer = Writer::factory()->create(['name' => 'George Orwell']);

    expect(resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, 'george orwell')->getKey())
        ->toBe($writer->getKey());
});

it('creates the record when no name matches', function (): void {
    $created = resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, 'Ursula K. Le Guin');

    expect($created->exists)->toBeTrue()
        ->and($created->getAttribute('name'))->toBe('Ursula K. Le Guin');
});

it('lets the model generate its own slug', function (): void {
    $created = resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, 'Ursula K. Le Guin');

    expect($created->getAttribute('slug'))->toBe('ursula-k-le-guin');
});

it('works for publishers too', function (): void {
    $created = resolve(FindOrCreateNamedRecord::class)->handle(Publisher::class, 'Signet Classics');

    expect($created)->toBeInstanceOf(Publisher::class)
        ->and(Publisher::count())->toBe(1);
});

it('takes the lowest id when two records share a name', function (): void {
    $first = Writer::factory()->create(['name' => 'Duplicate Name']);
    Writer::factory()->create(['name' => 'Duplicate Name']);

    expect(resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, 'Duplicate Name')->getKey())
        ->toBe($first->getKey());
});

it('trims surrounding whitespace before matching', function (): void {
    $writer = Writer::factory()->create(['name' => 'George Orwell']);

    expect(resolve(FindOrCreateNamedRecord::class)->handle(Writer::class, '  George Orwell  ')->getKey())
        ->toBe($writer->getKey());
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=FindOrCreateNamedRecord`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the action**

Create `app/Actions/Library/FindOrCreateNamedRecord.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Library;

use Illuminate\Database\Eloquent\Model;

/**
 * Reuse a writer or publisher by name, or create one carrying just that name.
 *
 * Matching relies on MySQL's case insensitive default collation rather than
 * wrapping the column in LOWER(), which would stop an index being used. Neither
 * table has a unique index on name, so a tie goes to the lowest id.
 */
final class FindOrCreateNamedRecord
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function handle(string $modelClass, string $name): Model
    {
        $name = trim($name);

        $existing = $modelClass::query()->where('name', $name)->orderBy('id')->first();

        if ($existing instanceof Model) {
            return $existing;
        }

        return $modelClass::create(['name' => $name]);
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=FindOrCreateNamedRecord`
Expected: PASS, 7 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Actions/Library/FindOrCreateNamedRecord.php tests/Feature/Actions/Library/FindOrCreateNamedRecordTest.php
git commit -m "feat: reuse or create a writer or publisher by name" -- app/Actions/Library/FindOrCreateNamedRecord.php tests/Feature/Actions/Library/FindOrCreateNamedRecordTest.php
```

---

### Task 7: `ResolveIsbn`

**Files:**
- Create: `app/Actions/Library/ResolveIsbn.php`
- Test: `tests/Feature/Actions/Library/ResolveIsbnTest.php`

**Interfaces:**
- Consumes: `ResolvesIsbn` (Task 4), `DownloadCover` (Task 5).
- Produces: `ResolveIsbn::handle(Isbn $isbn): array` with keys `duplicate`, `values`, `writer`, `publisher`, `image`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Actions/Library/ResolveIsbnTest.php`:

```php
<?php

declare(strict_types=1);

use App\Actions\Library\ResolveIsbn;
use App\Contracts\ResolvesIsbn;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\Writer;
use App\Support\BookMetadata;
use App\Support\Isbn;
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
        coverId: null,
    );
}

beforeEach(function (): void {
    Storage::fake('public');
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
        ->and($result['values'])->toBe([]);
});

it('returns an empty result when the resolver finds nothing', function (): void {
    fakeResolver(null);

    $result = resolve(ResolveIsbn::class)->handle(anIsbn());

    expect($result['values'])->toBe([])
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

    Illuminate\Support\Facades\Http::fake(['covers.openlibrary.org/*' => Illuminate\Support\Facades\Http::response(
        Illuminate\Http\Testing\File::image('cover.jpg')->getContent(), 200, ['Content-Type' => 'image/jpeg'],
    )]);

    expect(resolve(ResolveIsbn::class)->handle(anIsbn())['image'])->toStartWith('books/');
});

it('succeeds without an image when the cover cannot be fetched', function (): void {
    fakeResolver(new BookMetadata(title: 'No Cover', coverId: 999_999_999));

    Illuminate\Support\Facades\Http::fake(['covers.openlibrary.org/*' => Illuminate\Support\Facades\Http::response('', 404)]);

    $result = resolve(ResolveIsbn::class)->handle(anIsbn());

    expect($result['image'])->toBeNull()
        ->and($result['values']['name'])->toBe('No Cover');
});
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=ResolveIsbnTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the action**

Create `app/Actions/Library/ResolveIsbn.php`:

```php
<?php

declare(strict_types=1);

namespace App\Actions\Library;

use App\Contracts\ResolvesIsbn;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\Writer;
use App\Support\Isbn;
use Illuminate\Database\Eloquent\Model;

/**
 * One lookup, reported in the shape the book form consumes.
 *
 * Nothing is written here beyond a downloaded cover file. Writers and
 * publishers are matched, never created: the form offers the name and the user
 * decides, so a misspelling in Open Library cannot quietly populate the library.
 */
final readonly class ResolveIsbn
{
    public function __construct(
        private ResolvesIsbn $resolver,
        private DownloadCover $downloadCover,
    ) {}

    /**
     * @return array{
     *     duplicate: array{id: int, name: string}|null,
     *     values: array<string, mixed>,
     *     writer: array{id: int, name: string}|array{suggestion: string}|null,
     *     publisher: array{id: int, name: string}|array{suggestion: string}|null,
     *     image: string|null,
     * }
     */
    public function handle(Isbn $isbn): array
    {
        $existing = Book::query()->where('isbn', $isbn->value())->first();

        if ($existing instanceof Book) {
            return [
                'duplicate' => ['id' => $existing->id, 'name' => (string) $existing->name],
                'values' => [],
                'writer' => null,
                'publisher' => null,
                'image' => null,
            ];
        }

        $metadata = $this->resolver->resolve($isbn);

        if ($metadata === null) {
            return ['duplicate' => null, 'values' => [], 'writer' => null, 'publisher' => null, 'image' => null];
        }

        $values = array_filter([
            'name' => $metadata->title,
            'page_count' => $metadata->pageCount,
            'publication_date' => $metadata->publicationYear,
            'publication_location' => $metadata->publicationLocation,
        ], fn (mixed $value): bool => $value !== null);

        $values['isbn'] = $isbn->value();

        return [
            'duplicate' => null,
            'values' => $values,
            'writer' => $this->match(Writer::class, $metadata->authorName),
            'publisher' => $this->match(Publisher::class, $metadata->publisherName),
            'image' => $metadata->coverId === null ? null : $this->downloadCover->handle($metadata->coverId),
        ];
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array{id: int, name: string}|array{suggestion: string}|null
     */
    private function match(string $modelClass, ?string $name): ?array
    {
        if ($name === null) {
            return null;
        }

        $record = $modelClass::query()->where('name', $name)->orderBy('id')->first();

        if ($record instanceof Model) {
            return ['id' => (int) $record->getKey(), 'name' => (string) $record->getAttribute('name')];
        }

        return ['suggestion' => $name];
    }
}
```

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=ResolveIsbnTest`
Expected: PASS, 11 tests.

The `values` assertions use `toBe`, which compares key order. If they fail on
ordering rather than content, keep the key order exactly as written above.

- [ ] **Step 5: Verify the gates**

Run: `composer lint && composer types:check && composer type-coverage`

- [ ] **Step 6: Commit**

```bash
git add app/Actions/Library/ResolveIsbn.php tests/Feature/Actions/Library/ResolveIsbnTest.php
git commit -m "feat: resolve an ISBN into form values and relation matches" -- app/Actions/Library/ResolveIsbn.php tests/Feature/Actions/Library/ResolveIsbnTest.php
```

---

### Task 8: The endpoints

**Files:**
- Create: `app/Http/Controllers/Admin/Library/IsbnLookupController.php`
- Modify: `routes/admin.php:63-65`
- Test: `tests/Feature/Admin/Library/IsbnLookupTest.php`

**Interfaces:**
- Consumes: `ResolveIsbn` (Task 7), `FindOrCreateNamedRecord` (Task 6), `Isbn` (Task 1).
- Produces: `POST admin/books/isbn` named `admin.books.isbn`, and `POST admin/books/isbn/relation` named `admin.books.isbn-relation`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Admin/Library/IsbnLookupTest.php`:

```php
<?php

declare(strict_types=1);

use App\Contracts\ResolvesIsbn;
use App\Models\Book;
use App\Models\User;
use App\Models\Writer;
use App\Support\BookMetadata;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));

    $stub = Mockery::mock(ResolvesIsbn::class);
    $stub->shouldReceive('resolve')->andReturn(new BookMetadata(
        title: 'Nineteen Eighty-Four',
        authorName: 'George Orwell',
        publisherName: 'Signet Classics',
        publicationYear: 1993,
        publicationLocation: 'New York',
        pageCount: 328,
    ));
    app()->instance(ResolvesIsbn::class, $stub);
});

it('returns the resolved values for a valid isbn', function (): void {
    $this->postJson(route('admin.books.isbn'), ['isbn' => '978-0-451-52493-5'])
        ->assertOk()
        ->assertJsonPath('values.name', 'Nineteen Eighty-Four')
        ->assertJsonPath('values.isbn', '9780451524935')
        ->assertJsonPath('writer.suggestion', 'George Orwell');
});

it('rejects an isbn whose check digit is wrong, without asking Open Library', function (string $isbn): void {
    $this->postJson(route('admin.books.isbn'), ['isbn' => $isbn])
        ->assertStatus(422)
        ->assertJsonValidationErrors('isbn');
})->with(['9780451524936', 'not-an-isbn', '', '978045152493']);

it('reports a book that is already in the library', function (): void {
    $existing = Book::factory()->create(['isbn' => '9780451524935', 'name' => 'Already Here']);

    $this->postJson(route('admin.books.isbn'), ['isbn' => '9780451524935'])
        ->assertOk()
        ->assertJsonPath('duplicate.id', $existing->id)
        ->assertJsonPath('duplicate.name', 'Already Here');
});

it('creates a writer from a suggestion', function (): void {
    $this->postJson(route('admin.books.isbn-relation'), ['type' => 'writer', 'name' => 'George Orwell'])
        ->assertOk()
        ->assertJsonPath('name', 'George Orwell');

    expect(Writer::where('name', 'George Orwell')->exists())->toBeTrue();
});

it('reuses an existing writer rather than creating a second', function (): void {
    $writer = Writer::factory()->create(['name' => 'George Orwell']);

    $this->postJson(route('admin.books.isbn-relation'), ['type' => 'writer', 'name' => 'George Orwell'])
        ->assertOk()
        ->assertJsonPath('id', $writer->id);

    expect(Writer::count())->toBe(1);
});

it('refuses a relation type outside writer and publisher', function (string $type): void {
    $this->postJson(route('admin.books.isbn-relation'), ['type' => $type, 'name' => 'Anything'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('type');
})->with(['book', 'user', 'App\Models\User', 'category']);

it('requires a name for the relation', function (): void {
    $this->postJson(route('admin.books.isbn-relation'), ['type' => 'writer', 'name' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('name');
});

it('turns a guest away from both endpoints', function (string $routeName, array $payload): void {
    auth()->logout();

    $this->postJson(route($routeName), $payload)->assertUnauthorized();
})->with([
    'lookup' => ['admin.books.isbn', ['isbn' => '9780451524935']],
    'relation' => ['admin.books.isbn-relation', ['type' => 'writer', 'name' => 'X']],
]);
```

The `refuses a relation type outside writer and publisher` test is the one that
matters: models are unguarded, and without that whitelist this endpoint becomes
a way to create arbitrary records.

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=IsbnLookupTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/Admin/Library/IsbnLookupController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Library;

use App\Actions\Library\FindOrCreateNamedRecord;
use App\Actions\Library\ResolveIsbn;
use App\Http\Controllers\Controller;
use App\Models\Publisher;
use App\Models\Writer;
use App\Support\Isbn;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class IsbnLookupController extends Controller
{
    /**
     * The two model classes this endpoint may create.
     *
     * Models are unguarded, so without this map the relation endpoint would be
     * a way to create any record in the application.
     *
     * @var array<string, class-string<\Illuminate\Database\Eloquent\Model>>
     */
    private const array RELATIONS = [
        'writer' => Writer::class,
        'publisher' => Publisher::class,
    ];

    public function store(Request $request, ResolveIsbn $resolveIsbn): JsonResponse
    {
        /** @var array{isbn: string} $validated */
        $validated = $request->validate(['isbn' => ['required', 'string', 'max:20']]);

        $isbn = Isbn::tryFrom($validated['isbn']);

        if ($isbn === null) {
            throw ValidationException::withMessages([
                'isbn' => 'That is not a valid ISBN. Check the digits and try again.',
            ]);
        }

        return response()->json($resolveIsbn->handle($isbn));
    }

    public function relation(Request $request, FindOrCreateNamedRecord $findOrCreate): JsonResponse
    {
        /** @var array{type: string, name: string} $validated */
        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(array_keys(self::RELATIONS))],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $record = $findOrCreate->handle(self::RELATIONS[$validated['type']], $validated['name']);

        return response()->json([
            'id' => $record->getKey(),
            'name' => $record->getAttribute('name'),
        ]);
    }
}
```

- [ ] **Step 4: Register the routes**

In `routes/admin.php`, add the import beside the other Library controllers:

```php
use App\Http\Controllers\Admin\Library\IsbnLookupController;
```

Then, immediately **before** line 65's `Route::resource('books', …)`:

```php
Route::post('books/isbn', [IsbnLookupController::class, 'store'])->name('books.isbn');
Route::post('books/isbn/relation', [IsbnLookupController::class, 'relation'])->name('books.isbn-relation');
```

Order matters for the same reason the bulk routes come first: `books/isbn` must
be matched before `books/{book}` can swallow it.

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=IsbnLookupTest`
Expected: PASS.

- [ ] **Step 6: Confirm the route order is right**

Run: `php artisan route:list --name=books`
Expected: `admin.books.isbn` and `admin.books.isbn-relation` appear **above**
`admin.books.show`/`update`. If a lookup returns 404 instead of JSON, the
resource route is matching first.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Admin/Library/IsbnLookupController.php routes/admin.php tests/Feature/Admin/Library/IsbnLookupTest.php
git commit -m "feat: add the ISBN lookup endpoints" -- app/Http/Controllers/Admin/Library/IsbnLookupController.php routes/admin.php tests/Feature/Admin/Library/IsbnLookupTest.php
```

---

### Task 9: The `isbn` field type

**Files:**
- Modify: `app/Forms/Field.php`, `app/Forms/Definitions/BookForm.php`
- Test: `tests/Feature/Forms/ResourceFormTest.php`

**Interfaces:**
- Produces: `Field::isbn(string $key): self`, emitting `type => 'isbn'` in `schema()`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Forms/ResourceFormTest.php`:

```php
it('declares an isbn field that the form renders with a lookup button', function (): void {
    $field = App\Forms\Field::isbn('isbn')->schema();

    expect($field['type'])->toBe('isbn')
        ->and($field['key'])->toBe('isbn');
});

it('puts the isbn field on the book form', function (): void {
    $keys = array_column((new App\Forms\Definitions\BookForm)->schema()['fields'], 'key');

    expect($keys)->toContain('isbn');
});

it('does not offer the isbn field for bulk editing', function (): void {
    expect((new App\Forms\Definitions\BookForm)->bulkEditableFields())->not->toContain('isbn');
});
```

The third test matters: `isbn` is unique, so setting one value across a
selection would violate the constraint on every row after the first.

- [ ] **Step 2: Run it and watch it fail**

Run: `php artisan test --filter=ResourceFormTest`
Expected: FAIL — `Field::isbn()` does not exist.

- [ ] **Step 3: Add the factory method**

In `app/Forms/Field.php`, directly after `public static function text()`:

```php
/**
 * An ISBN with a lookup button beside it.
 *
 * Its own type rather than a text field because ResourceForm.vue has no slots,
 * so the Fetch affordance cannot be layered on from the page. Being part of the
 * schema, it appears on Create and Edit alike with no page changes.
 */
public static function isbn(string $key): self
{
    return new self($key, 'isbn');
}
```

- [ ] **Step 4: Put it on the book form**

In `app/Forms/Definitions/BookForm.php`, insert after the `slug` line:

```php
Field::isbn('isbn')->label('ISBN')->help('Paste an ISBN and press Fetch to fill the form.')->columnSpan(2),
```

- [ ] **Step 5: Confirm it is not bulk editable**

`bulkEditableFields()` allows `select`, `multiselect`, `toggle`, `date`,
`datetime`, `number` and `money`. The new `isbn` type is none of those, so it is
excluded automatically. The test added in Step 1 pins that.

- [ ] **Step 6: Run the tests**

Run: `php artisan test --filter="ResourceFormTest|LibraryResources"`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Forms/ tests/Feature/Forms/ResourceFormTest.php
git commit -m "feat: add an isbn field type to the form contract" -- app/Forms/ tests/Feature/Forms/ResourceFormTest.php
```

---

### Task 10: The Fetch button

**Files:**
- Modify: `resources/js/Components/Form/FormField.vue`
- Test: `tests/Browser/AdminSmokeTest.php`

**Interfaces:**
- Consumes: `admin.books.isbn` and `admin.books.isbn-relation` from Task 8, and the `isbn` field type from Task 9.

- [ ] **Step 1: Add the imports**

In the `<script setup>` block of `resources/js/Components/Form/FormField.vue`,
beside the existing PrimeVue imports:

```js
import Button from 'primevue/button'
import axios from 'axios'
```

`axios` is already a dependency and Laravel configures its CSRF header.

- [ ] **Step 2: Add the lookup state**

After `function update(value) { … }` in the same block:

```js
const looking = ref(false)
const lookupMessage = ref(null)
const duplicate = ref(null)
```

Widen the existing emits declaration. The current line reads
`const emit = defineEmits(['update:modelValue'])`; replace it with:

```js
const emit = defineEmits(['update:modelValue', 'resolved'])
```

Then add the lookup itself:

```js
async function lookupIsbn() {
  if (!props.modelValue) {
    return
  }

  looking.value = true
  lookupMessage.value = null
  duplicate.value = null

  try {
    const { data } = await axios.post(route('admin.books.isbn'), { isbn: props.modelValue })

    if (data.duplicate) {
      duplicate.value = data.duplicate
      return
    }

    if (!data.values || Object.keys(data.values).length === 0) {
      lookupMessage.value = 'No record for that ISBN.'
      return
    }

    emit('resolved', data)
  } catch (error) {
    lookupMessage.value =
      error.response?.status === 422
        ? (error.response.data.errors?.isbn?.[0] ?? 'That ISBN is not valid.')
        : 'Could not reach Open Library.'
  } finally {
    looking.value = false
  }
}
```

- [ ] **Step 3: Render the field**

In the template, immediately before the final fallback `<InputText v-else …>`:

```html
<div v-else-if="field.type === 'isbn'" class="flex flex-col gap-2">
  <div class="flex gap-2">
    <InputText
      :id="field.key"
      :model-value="modelValue"
      :disabled="field.disabled"
      :invalid="Boolean(error)"
      placeholder="978-0-451-52493-5"
      fluid
      @update:model-value="update"
    />
    <Button
      label="Fetch"
      icon="pi pi-search"
      severity="secondary"
      outlined
      :loading="looking"
      :disabled="!modelValue"
      @click="lookupIsbn"
    />
  </div>

  <small v-if="duplicate" class="text-surface-500">
    Already in the library as
    <a class="underline" :href="route('admin.books.edit', duplicate.id)">{{ duplicate.name }}</a>
  </small>

  <small v-if="lookupMessage" class="text-surface-500">{{ lookupMessage }}</small>
</div>
```

- [ ] **Step 4: Apply the resolved values in the form**

In `resources/js/Components/Form/ResourceForm.vue`, on the `<FormField>` element,
add the handler:

```html
@resolved="applyResolved"
```

And in its `<script setup>`:

```js
function applyResolved(data) {
  // A fetch fills the blanks; anything already typed is the user's and stays.
  Object.entries(data.values ?? {}).forEach(([key, value]) => {
    if (props.form[key] === null || props.form[key] === '' || props.form[key] === undefined) {
      props.form[key] = value
    }
  })

  if (data.image) {
    props.form.image = data.image
  }

  ;['writer', 'publisher'].forEach((relation) => {
    const match = data[relation]
    const key = `${relation}_id`

    if (match?.id && !props.form[key]) {
      props.form[key] = match.id
    }
  })
}
```

- [ ] **Step 5: Check the frontend gates**

Run: `npm run lint:check && npm run format:check && npm run build`
Expected: all clean.

If ESLint reports `vue/no-mutating-props`, the rule is configured `shallowOnly`
and writing into `props.form` is the established pattern here — confirm you are
writing a key **into** the form object, not reassigning `props.form` itself.

- [ ] **Step 6: Add the browser test**

Append to `tests/Browser/AdminSmokeTest.php`:

```php
it('shows the isbn field with its fetch button on the book form', function (): void {
    visit(route('admin.books.create'))
        ->assertNoJavaScriptErrors()
        ->assertSee('ISBN')
        ->assertSee('Fetch');
});
```

- [ ] **Step 7: Run the tests**

Run: `php artisan test`
Expected: PASS.

- [ ] **Step 8: Commit and push**

```bash
git add resources/js tests/Browser/AdminSmokeTest.php
git commit -m "feat: add the ISBN fetch button to the book form" -- resources/js tests/Browser/AdminSmokeTest.php
git push origin master
```

---

### Task 11: Release

**Files:**
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Run every gate from a clean state**

```bash
composer ci:check
npm run lint:check
npm run format:check
npm run build
```

- [ ] **Step 2: Run against the CI environment**

```bash
cp .env .env.bak && cp .env.example .env && php artisan key:generate --quiet
php artisan test
cp .env.bak .env && rm .env.bak
```

Expected: identical results. A difference means a test depends on a value only
your local `.env` carries — set it with `config([...])` inside the test instead.

- [ ] **Step 3: Write the changelog entry**

Add above the newest released version in `CHANGELOG.md`:

```markdown
## [0.7.0] - 2026-09-20

### Added

- ISBN lookup on the book form. Paste an ISBN, press Fetch, and the title,
  publisher, year, place, page count and cover fill in from Open Library.
  Nothing is written until you save, and fields you have already typed into
  are left alone.
- A unique `books.isbn` column, so a lookup can say the book is already in the
  library instead of letting a duplicate be created.
- `App\Support\Isbn`, which validates the check digit before any request is
  made, so a typo fails instantly rather than after a round trip.

### Notes

- Open Library's `/api/books?jscmd=data` endpoint returns 404 and is not used.
  The edition record supplies the facts and `search.json` supplies only the
  author name, because its publisher and year are aggregates across every
  edition of a work.
- Cover requests send `?default=false`. Without it a missing cover returns
  HTTP 200 carrying a blank placeholder image.
```

- [ ] **Step 4: Commit, tag and release**

```bash
git add CHANGELOG.md
git commit -m "docs: release v0.7.0" -- CHANGELOG.md
git push origin master
git tag -a v0.7.0 -m "ISBN resolver"
git push origin v0.7.0
```

- [ ] **Step 5: Wait for CI and confirm it is green**

```bash
gh run list --limit 1 --workflow=ci.yml
```

Expected: all six jobs succeed before the release is published.

---

## Verification Summary

After Task 11, all of the following must hold:

```bash
composer ci:check        # pint, phpstan max, rector dry-run, 100% type coverage, tests
npm run lint:check
npm run format:check
```

- `php artisan route:list --name=books` shows `admin.books.isbn` **above** the
  resource routes.
- `App\Support\Isbn` rejects every invalid ISBN in its dataset, including a
  transposed pair that breaks only the checksum.
- `OpenLibraryServiceTest` proves two resolves of the same ISBN produce two
  requests, not four.
- `DownloadCoverTest` proves the request carries `default=false`.
- `IsbnLookupTest` proves the relation endpoint refuses any type other than
  `writer` and `publisher`.
- Every new class under `app/` is `final`; `tests/Feature/ArchTest.php` passes.
- `tests/Feature/Actions/Resources/BulkWriteSafetyTest.php` still passes — no
  new bulk write bypasses the models.

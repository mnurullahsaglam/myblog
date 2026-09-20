# ISBN Resolver — Design

**Date:** 2026-09-20
**Status:** Approved for planning
**Baseline:** `v0.6.1`

## Goal

Paste an ISBN on the book form, press Fetch, and have the title, publisher, year,
place, page count and cover filled in from Open Library. Nothing is written
until the form is saved, so a wrong lookup costs nothing.

## Non-Goals

- **Google Books fallback.** Its documentation requires an API key and this
  project has no key management. Revisit only if Open Library's coverage proves
  too thin in use.
- **Bulk import.** One ISBN at a time, from the form.
- **Backfilling existing books.** The three current rows keep a null ISBN.
- **Author biography, subjects, descriptions.** Only the fields `books` already has.

## Verified API Behaviour

Established by direct request on 2026-09-20. Recording it here because two of
the three findings contradict what the public documentation and most tutorials
suggest.

| Endpoint | Result |
| --- | --- |
| `GET /api/books?bibkeys=ISBN:…&jscmd=data` | **404.** The endpoint most examples recommend is gone. Do not use it. |
| `GET /isbn/{isbn}.json` | **302** to the edition record. The client must follow redirects. |
| `GET /search.json?q=isbn:…` | 200, resolves author names inline. |

### Why two requests are needed

The edition record is the only source of facts about *this* ISBN:

```
title            "Nineteen Eighty-Four"
publishers       ["Signet Classics"]
publish_date     "1993?"
publish_places   ["New York"]
number_of_pages  328
covers           [12054527]
isbn_13          ["9780451524935"]
```

It carries no dependable author name. `search.json` does resolve
`author_name: ["George Orwell"]`, but everything else it returns is aggregated
across every edition of the work:

```
publisher              ["Kaktos", "Ullstein TB-Vlg.", "GALLIMARD", "Ren Kitap", …]
publish_place          ["Taibei Xian Zhonghe Shi", "Fairfield", "São Paulo", …]
first_publish_year     1949          (the work; this edition is 1993)
number_of_pages_median 318           (a median, not this edition's 328)
```

Using `search.json` for edition facts would fill the form with the wrong
publisher. So: **the edition record for the facts, `search.json` for the author
name alone.**

### Covers

`https://covers.openlibrary.org/b/id/{coverId}-L.jpg`

A **missing** cover returns `HTTP 200` with a 43-byte placeholder image. Adding
`?default=false` makes it `404` instead. Without that parameter the application
would silently store blank covers. A real cover `302`s to a CDN, so redirects
must be followed here too.

### Politeness

Open Library allows 1 request per second, or 3 when the caller identifies
itself. Every request sends `User-Agent: myblog/1.0 (<contact email>)`, from a
new `services.open_library.contact` config value. Responses are cached for 30
days keyed by the normalised ISBN, so a re-fetch costs nothing.

---

## Schema

One migration:

```php
Schema::table('books', function (Blueprint $table): void {
    $table->string('isbn', 13)->nullable()->unique()->after('slug');
});
```

Nullable because existing books have none. Unique because it is what lets Fetch
say "you already have this book" instead of creating a duplicate. Stored
normalised: digits only, ISBN-13 form, no hyphens.

---

## Units

Each is independently testable and has one job.

### `App\Support\Isbn`

A value object. `Isbn::tryFrom(string): ?self` strips hyphens and spaces,
validates the **check digit** for ISBN-10 and ISBN-13, and converts 10 to 13.
`->value(): string` returns the 13-digit form.

Pure, no I/O. An invalid ISBN is rejected before any request is made, which
means a typo produces an immediate field error rather than a slow 404.

### `App\Contracts\ResolvesIsbn`

```php
interface ResolvesIsbn
{
    public function resolve(Isbn $isbn): ?BookMetadata;
}
```

Returns `null` when Open Library has no record. Keeps the service final and
substitutable, matching `ConvertsCurrency`, `SyncsGitHubIssues` and
`NotifiesAdmin`.

### `App\Support\BookMetadata`

A readonly DTO: `title`, `authorName`, `publisherName`, `publicationYear`,
`publicationLocation`, `pageCount`, `coverId` — every field nullable except
`title`, because Open Library records are uneven. An edition record without a
title is treated as no record at all, so `resolve()` returns `null` rather than
a DTO that cannot fill the one field the form requires.

### `App\Services\OpenLibraryService`

The only class that knows about HTTP, the redirect, the two endpoints and the
cache. Implements `ResolvesIsbn`. A 5-second timeout with one retry; a transport
failure returns `null` rather than throwing, because a lookup failing is not an
error the user needs a stack trace for.

**Year parsing.** `publish_date` is free text — `"1993?"`, `"c1993"`,
`"March 1993"`, `"1993-06"`. The parser takes the first four-digit sequence in
1000–(current year) and returns `null` otherwise. It never guesses: a blank year
is better than a wrong one, and `books.publication_date` is a `YEAR` column.

### `App\Actions\Library\ResolveIsbn`

Orchestrates one lookup: validate the ISBN, check whether a book already holds
it, call the service, match writer and publisher by name.

Returns an array shape the form consumes:

```php
[
  'duplicate' => ['id' => 4, 'name' => 'Nineteen Eighty-Four'] | null,
  'values'    => ['name' => …, 'page_count' => …, 'publication_date' => …,
                  'publication_location' => …],
  'writer'    => ['id' => 2, 'name' => 'George Orwell'] | ['suggestion' => 'George Orwell'] | null,
  'publisher' => ['id' => 7, 'name' => 'Signet Classics'] | ['suggestion' => 'Signet Classics'] | null,
  'coverId'   => 12054527 | null,
]
```

**Matching** is case-insensitive on `name`. MySQL's default collation is
already case-insensitive, so this is a plain `where('name', $name)` — wrapping
it in `LOWER()` would only stop an index being used. Neither `writers` nor
`publishers` has a unique index on `name`, so a tie is resolved by lowest id.
No row is ever created here.

### `App\Actions\Library\FindOrCreateNamedRecord`

Backs the Create button next to an unmatched suggestion. Takes a model class and
a name, returns the existing row or creates one with just that name, letting the
model's own slug generation run. Restricted by the controller to `Writer` and
`Publisher`, because models are unguarded and this must not become a way to
create arbitrary records.

### `App\Actions\Library\DownloadCover`

Fetches `…/{coverId}-L.jpg?default=false`, follows redirects, and stores the
result on the `public` disk under `books/` exactly as a manual upload does.
Rejects a response that is not an image or exceeds 5 MB, matching the form's own
`image|max:5120` rule. Returns the stored path, or `null` on any failure — a
missing cover must not fail the lookup.

### `Field::isbn()` and its renderer

`ResourceForm.vue` has no slots, so the Fetch affordance cannot be layered on
from the page. It becomes a field type instead: `Field::isbn('isbn')` renders as
a text input with a Fetch button, handled in `FormField.vue` beside the existing
types. Being server-driven, it appears on both Create and Edit with no page
changes.

---

## Flow

1. Paste an ISBN, press Fetch.
2. `POST admin/books/isbn` → `{isbn}` → `ResolveIsbn`.
3. Invalid check digit → 422 on `isbn`. No request leaves the app.
4. Already in the library → the form shows "Already added as …" with a link. No
   fields are touched.
5. Otherwise the resolved values populate empty fields. **Fields you have
   already typed into are left alone** — a fetch never overwrites your work.
   This rule lives in the Vue component, which compares each resolved key
   against the current form value and skips any that is already non-empty. The
   endpoint always returns everything it found; choosing what to apply is the
   form's job.
6. Writer and publisher select themselves when matched; otherwise the suggested
   name appears with a Create button calling `POST admin/books/isbn/relation`.
7. A cover id triggers `DownloadCover`; the stored path fills the image field and
   a preview appears.
8. Nothing is persisted until Create or Save.

---

## Error Handling

| Condition | Result |
| --- | --- |
| Malformed or bad check digit | 422 on `isbn`, no request made |
| Open Library has no record | 200 with a flash: "No record for that ISBN" |
| Timeout or transport failure | 200 with a flash: "Could not reach Open Library" |
| Cover missing or too large | Lookup succeeds, image field left empty |
| ISBN already on another book | 200 with the duplicate, no fields changed |

No path returns a 500, and no path leaves the form half-populated.

---

## Testing

Nuno-style: name the behaviour, cover the edges first, `Http::fake()` at the
boundary, never mock our own code.

- **`Isbn`** is the densest unit and gets a dataset: valid ISBN-13, valid
  ISBN-10, ISBN-10 with an `X` check digit, hyphenated, spaced, one digit short,
  a transposed pair that breaks the checksum, `978` prefixed nonsense, empty.
  Plus 10→13 conversion equality.
- **Year parsing** gets its own dataset: `"1993"`, `"1993?"`, `"c1993"`,
  `"March 1993"`, `"1993-06"`, `"n.d."`, `""`, `"12345"`, and a year beyond the
  current one.
- **`OpenLibraryService`** with `Http::fake()`: the redirect is followed; a
  404 edition returns null; author comes from `search.json`; the second call is
  served from cache with `Http::assertSentCount(2)` across two resolves.
- **`ResolveIsbn`**: matched writer, unmatched writer yields a suggestion,
  case-insensitive match, tie broken by lowest id, duplicate ISBN detected.
- **`DownloadCover`**: stores a faked image; returns null on 404; rejects a
  non-image body; rejects one over 5 MB; asserts the request URL carries
  `default=false`, because that parameter is the whole defence against storing
  blank covers.
- **Endpoint tests**: validation failure, duplicate, success, guest redirected.
- **One browser test**: the ISBN field renders with its button and the page
  reports no JavaScript errors.

## Risks

| Risk | Mitigation |
| --- | --- |
| Open Library changes endpoints again | One class knows the endpoints; the verified behaviour is recorded above |
| Coverage is thin for Turkish editions | Lookup is optional; every field stays hand-editable. Measure before adding a fallback |
| `FindOrCreateNamedRecord` becomes a general write primitive | Controller restricts it to Writer and Publisher |
| Cover download is a request to a third-party URL | Fixed host, fixed path shape built from an integer id; never a user-supplied URL |

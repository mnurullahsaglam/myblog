<?php

declare(strict_types=1);

namespace App\Actions\Library;

use App\Contracts\ResolvesIsbn;
use App\Models\Book;
use App\Models\Publisher;
use App\Models\Writer;
use App\Support\BookMetadata;
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

        $duplicate = $this->identify($existing);

        if ($duplicate !== null) {
            return [
                'duplicate' => $duplicate,
                'values' => [],
                'writer' => null,
                'publisher' => null,
                'image' => null,
            ];
        }

        $metadata = $this->resolver->resolve($isbn);

        if (! $metadata instanceof BookMetadata) {
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
     * A record's id and name, or null when it is absent or not the shape the
     * form needs. Eloquent types both as mixed, and casting mixed is exactly the
     * guess static analysis is right to reject.
     *
     * @return array{id: int, name: string}|null
     */
    private function identify(?Model $record): ?array
    {
        if (! $record instanceof Model) {
            return null;
        }

        $id = $record->getKey();
        $name = $record->getAttribute('name');

        return is_int($id) && is_string($name) ? ['id' => $id, 'name' => $name] : null;
    }

    /**
     * Lowercases both sides rather than trusting the database collation, for the
     * same reason FindOrCreateNamedRecord does: MySQL and SQLite disagree.
     *
     * @param  class-string<Model>  $modelClass
     * @return array{id: int, name: string}|array{suggestion: string}|null
     */
    private function match(string $modelClass, ?string $name): ?array
    {
        if ($name === null) {
            return null;
        }

        $record = $modelClass::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->orderBy('id')
            ->first();

        return $this->identify($record) ?? ['suggestion' => $name];
    }
}

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

        $title = $this->stringOrNull($payload['title'] ?? null);

        if ($title === null) {
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

        return is_array($first) ? $this->stringOrNull($this->firstOf($first['author_name'] ?? null)) : null;
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

    private function year(mixed $value): ?int
    {
        if (! is_string($value)) {
            return null;
        }

        if (preg_match('/(?<!\d)(1\d{3}|20\d{2})(?!\d)/', $value, $matches) !== 1) {
            return null;
        }

        $year = (int) $matches[1];

        return $year >= 1000 && $year <= (int) date('Y') ? $year : null;
    }
}

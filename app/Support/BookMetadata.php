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

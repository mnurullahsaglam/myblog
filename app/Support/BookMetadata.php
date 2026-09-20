<?php

declare(strict_types=1);

namespace App\Support;

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

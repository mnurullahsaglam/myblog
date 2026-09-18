<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Book;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class BookExport extends ResourceExport
{
    public function name(): string
    {
        return 'books';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['id', 'name', 'original_name', 'slug', 'writer', 'publisher', 'page_count', 'publication_date', 'publication_location', 'edition_number', 'categories'];
    }

    public function query(): Builder
    {
        return Book::query()->with(['writer', 'publisher', 'categories'])->orderBy('id');
    }

    /**
     * @return array<int, string|int|float|null>
     */
    public function row(Model $record): array
    {
        /** @var Book $record */
        return [
            $record->id,
            $record->name,
            $record->original_name,
            $record->slug,
            $record->writer?->name,
            $record->publisher?->name,
            $record->page_count,
            $record->publication_date,
            $record->publication_location,
            $record->edition_number,
            $record->categories->pluck('name')->implode(', '),
        ];
    }
}

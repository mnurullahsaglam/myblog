<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Publisher;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PublisherExport extends ResourceExport
{
    public function name(): string
    {
        return 'publishers';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['id', 'name', 'slug', 'books_count'];
    }

    public function query(): Builder
    {
        return Publisher::query()->withCount('books')->orderBy('name');
    }

    /**
     * @return array<int, string|int|float|null>
     */
    public function row(Model $record): array
    {
        /** @var Publisher $record */
        return [
            $record->id,
            $record->name,
            $record->slug,
            is_numeric($count = $record->getAttribute('books_count')) ? (int) $count : 0,
        ];
    }
}

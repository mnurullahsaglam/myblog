<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Writer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class WriterExport extends ResourceExport
{
    public function name(): string
    {
        return 'writers';
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return ['id', 'name', 'slug', 'birth_year', 'death_year', 'birth_place', 'death_place', 'books_count'];
    }

    public function query(): Builder
    {
        return Writer::query()->withCount('books')->orderBy('name');
    }

    /**
     * @return array<int, string|int|float|null>
     */
    public function row(Model $record): array
    {
        /** @var Writer $record */
        return [
            $record->id,
            $record->name,
            $record->slug,
            $record->birth_year,
            $record->death_year,
            $record->birth_place,
            $record->death_place,
            is_numeric($count = $record->getAttribute('books_count')) ? (int) $count : 0,
        ];
    }
}

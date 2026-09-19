<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\WakaTimeSummary;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Override;

final class WakaTimeSummaryTable extends ResourceTable
{
    #[Override]
    protected string $model = WakaTimeSummary::class;

    #[Override]
    protected array $withCount = ['entries'];

    #[Override]
    protected string $defaultSort = '-date';

    protected function columns(): array
    {
        return [
            Column::date('date')->sortable(),
            Column::text('duration')->label('Time coded')
                ->state(fn (WakaTimeSummary $record): string => $this->humanDuration((int) $record->total_seconds))
                ->align('right'),
            Column::count('total_seconds')->label('Seconds')->sortable()->toggleable(hiddenByDefault: true),
            Column::count('entries_count')->label('Entries'),
        ];
    }

    private function humanDuration(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0 ? $hours.'h '.$minutes.'m' : $minutes.'m';
    }

    protected function filters(): array
    {
        return [
            Filter::dateRange('date'),
        ];
    }

    protected function searchable(): array
    {
        return ['date'];
    }

    protected function titleColumn(): string
    {
        return 'date';
    }
}

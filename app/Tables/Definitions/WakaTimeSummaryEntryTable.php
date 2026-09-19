<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\WakaTimeSummaryEntry;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Builder;
use Override;

final class WakaTimeSummaryEntryTable extends ResourceTable
{
    #[Override]
    protected string $model = WakaTimeSummaryEntry::class;

    #[Override]
    protected string $defaultSort = '-seconds';

    #[Override]
    protected int $perPage = 50;

    public function __construct(private readonly int $summaryId) {}

    protected function query(): Builder
    {
        return WakaTimeSummaryEntry::query()->where('waka_time_summary_id', $this->summaryId);
    }

    protected function columns(): array
    {
        return [
            Column::badge('type')->color('secondary'),
            Column::text('name')->sortable(),
            Column::text('duration')->label('Time')
                ->state(function (WakaTimeSummaryEntry $record): string {
                    $seconds = (int) $record->seconds;

                    return intdiv($seconds, 3600).'h '.intdiv($seconds % 3600, 60).'m';
                })
                ->align('right'),
            Column::count('seconds')->sortable(),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('type', [
                WakaTimeSummaryEntry::TYPE_LANGUAGE => 'Language',
                WakaTimeSummaryEntry::TYPE_EDITOR => 'Editor',
                WakaTimeSummaryEntry::TYPE_PROJECT => 'Project',
                WakaTimeSummaryEntry::TYPE_OS => 'Operating system',
                WakaTimeSummaryEntry::TYPE_CATEGORY => 'Category',
            ])->multiple(),
        ];
    }

    protected function searchable(): array
    {
        return ['name'];
    }
}

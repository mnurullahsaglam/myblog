<?php

namespace App\Filament\Widgets;

use App\Models\WakaTimeSummaryEntry;

class WakaTimeCategoriesChart extends WakaTimeBreakdownChart
{
    protected ?string $heading = 'Categories';

    protected function breakdownType(): string
    {
        return WakaTimeSummaryEntry::TYPE_CATEGORY;
    }
}

<?php

namespace App\Filament\Widgets;

use App\Models\WakaTimeSummaryEntry;

class WakaTimeEditorsChart extends WakaTimeBreakdownChart
{
    protected ?string $heading = 'Editors';

    protected function breakdownType(): string
    {
        return WakaTimeSummaryEntry::TYPE_EDITOR;
    }
}

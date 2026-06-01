<?php

namespace App\Filament\Widgets;

use App\Models\WakaTimeSummaryEntry;

class WakaTimeLanguagesChart extends WakaTimeBreakdownChart
{
    protected ?string $heading = 'Languages';

    protected function breakdownType(): string
    {
        return WakaTimeSummaryEntry::TYPE_LANGUAGE;
    }
}

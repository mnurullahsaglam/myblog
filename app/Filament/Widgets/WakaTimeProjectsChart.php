<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\WakaTimeSummaryEntry;

class WakaTimeProjectsChart extends WakaTimeBreakdownChart
{
    protected ?string $heading = 'Projects';

    protected function breakdownType(): string
    {
        return WakaTimeSummaryEntry::TYPE_PROJECT;
    }
}

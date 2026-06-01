<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\WakaTimeSummaryEntry;

class WakaTimeOperatingSystemsChart extends WakaTimeBreakdownChart
{
    protected ?string $heading = 'Operating Systems';

    protected function breakdownType(): string
    {
        return WakaTimeSummaryEntry::TYPE_OS;
    }
}

<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithWakaTimeData;
use Filament\Widgets\ChartWidget;

abstract class WakaTimeBreakdownChart extends ChartWidget
{
    use InteractsWithWakaTimeData;

    protected static bool $isDiscovered = false;

    /** The WakaTimeSummaryEntry::TYPE_* this chart renders. */
    abstract protected function breakdownType(): string;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        return $this->doughnutData($this->breakdownSeconds($this->breakdownType()));
    }

    protected function getOptions(): array
    {
        return $this->doughnutOptions();
    }
}

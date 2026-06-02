<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithWakaTimeData;
use App\Models\WakaTimeSummary;
use Filament\Widgets\ChartWidget;

class WakaTimeWeekdayChart extends ChartWidget
{
    use InteractsWithWakaTimeData;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Coding time by weekday';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        // ISO weekday 1 (Mon) .. 7 (Sun)
        $totals = array_fill(1, 7, 0);

        $this->summaries()->each(function (WakaTimeSummary $s) use (&$totals): void {
            $totals[$s->date->dayOfWeekIso] += $s->total_seconds;
        });

        return [
            'datasets' => [[
                'label' => 'Hours',
                'data' => array_map(fn (int $sec): float => round($sec / 3600, 2), array_values($totals)),
                'backgroundColor' => '#3b82f6',
            ]],
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        ];
    }
}

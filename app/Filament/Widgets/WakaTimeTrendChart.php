<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithWakaTimeData;
use App\Models\WakaTimeSummary;
use Filament\Widgets\ChartWidget;

class WakaTimeTrendChart extends ChartWidget
{
    use InteractsWithWakaTimeData;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        return 'Coding time · ' . strtolower($this->rangeLabel());
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $summaries = $this->summaries();

        if ($summaries->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        $byDate = $summaries->keyBy(fn (WakaTimeSummary $s): string => $s->date->toDateString())
            ->map(fn (WakaTimeSummary $s): int => $s->total_seconds);

        // Walk every calendar day from the first day in range to today so gaps show as zero.
        $start = ($days = $this->rangeDays())
            ? now()->subDays($days - 1)->startOfDay()
            : $summaries->first()->date->copy()->startOfDay();

        $labels = [];
        $hours = [];

        for ($day = $start->copy(); $day->lte(now()); $day->addDay()) {
            $labels[] = $day->format('M j');
            $hours[] = round(($byDate[$day->toDateString()] ?? 0) / 3600, 2);
        }

        return [
            'datasets' => [[
                'label' => 'Hours coded',
                'data' => $hours,
                'borderColor' => '#f59e0b',
                'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $labels,
        ];
    }
}

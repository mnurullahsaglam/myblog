<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithWakaTimeData;
use App\Models\WakaTimeSummary;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WakaTimeOverview extends BaseWidget
{
    use InteractsWithWakaTimeData;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        return $this->isSingleDay() ? $this->dayStats() : $this->rangeStats();
    }

    /** @return array<int, Stat> */
    private function dayStats(): array
    {
        return [
            Stat::make('Total coding time', $this->formatDuration($this->totalSeconds()))
                ->description($this->record?->date->format('l, M j, Y'))
                ->color('success'),
            Stat::make('AI sessions', (string) (int) $this->grandTotalSum('ai_sessions'))
                ->description($this->formatNumber($this->grandTotalSum('ai_prompt_events_total')).' prompts')
                ->color('info'),
            Stat::make('Tokens', $this->formatNumber($this->grandTotalSum('ai_input_tokens') + $this->grandTotalSum('ai_output_tokens')))
                ->description($this->formatNumber($this->grandTotalSum('ai_input_tokens')).' in · '.$this->formatNumber($this->grandTotalSum('ai_output_tokens')).' out'),
            Stat::make('AI spend', '$'.number_format($this->grandTotalSum('ai_agent_total_cost'), 2))
                ->color('warning'),
        ];
    }

    /** @return array<int, Stat> */
    private function rangeStats(): array
    {
        $summaries = $this->summaries();
        $total = $this->totalSeconds();
        $today = $summaries->firstWhere(fn (WakaTimeSummary $s) => $s->date->isToday());
        $mostActive = $summaries->sortByDesc('total_seconds')->first();

        $sparkline = $summaries->map(fn (WakaTimeSummary $s): float => round($s->total_seconds / 3600, 2))->values()->all();
        $divisor = $this->rangeDays() ?? max(1, $summaries->count());

        return [
            Stat::make($this->rangeLabel(), $this->formatDuration($total))
                ->chart($sparkline ?: [0])
                ->color('success'),
            Stat::make('Daily average', $this->formatDuration((int) ($total / max(1, $divisor))))
                ->description('over '.strtolower($this->rangeLabel())),
            Stat::make('Today', $this->formatDuration($today?->total_seconds ?? 0))
                ->color('info'),
            Stat::make('Most active day', $mostActive ? $this->formatDuration($mostActive->total_seconds) : '—')
                ->description($mostActive?->date->format('D, M jS') ?? '—')
                ->color('warning'),
        ];
    }
}

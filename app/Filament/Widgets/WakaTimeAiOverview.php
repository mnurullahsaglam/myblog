<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithWakaTimeData;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WakaTimeAiOverview extends BaseWidget
{
    use InteractsWithWakaTimeData;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'AI coding';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $aiLines = $this->grandTotalSum('ai_additions');
        $humanLines = $this->grandTotalSum('human_additions');
        $totalLines = $aiLines + $humanLines;
        $aiShare = $totalLines > 0 ? round($aiLines / $totalLines * 100) : 0;

        return [
            Stat::make('AI-driven', $aiShare . '%')
                ->description('share of added lines')
                ->color('info'),
            Stat::make('AI lines', $this->formatNumber($aiLines))
                ->descriptionIcon('heroicon-m-sparkles')
                ->color('info'),
            Stat::make('Human lines', $this->formatNumber($humanLines))
                ->descriptionIcon('heroicon-m-user'),
            Stat::make('AI spend', '$' . number_format($this->grandTotalSum('ai_agent_total_cost'), 2))
                ->description($this->formatNumber($this->grandTotalSum('ai_input_tokens') + $this->grandTotalSum('ai_output_tokens')) . ' tokens')
                ->color('warning'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Support\WakaTime;

use App\Models\Repository;
use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;

/**
 * Shapes WakaTime aggregates into the series and tiles the dashboard renders.
 * The aggregation itself lives in AggregatesWakaTimeData; this only arranges it.
 */
final class DashboardData
{
    use AggregatesWakaTimeData;

    /** @var array<int, string> */
    public const array RANGES = ['7', '14', '30', '90', 'all'];

    private function __construct(private readonly ?string $range) {}

    /**
     * @return array{range: string, rangeLabel: string, tiles: array<int, array<string, mixed>>, trend: array<string, mixed>, weekday: array<string, mixed>, breakdowns: array<string, array<string, mixed>>, syncedAt: string|null}
     */
    public static function for(string $range): array
    {
        $range = in_array($range, self::RANGES, true) ? $range : '7';
        $instance = new self($range);

        return [
            'range' => $range,
            'rangeLabel' => $instance->rangeLabel(),
            'tiles' => $instance->tiles(),
            'trend' => $instance->trendSeries(),
            'weekday' => $instance->weekdaySeries(),
            'breakdowns' => [
                'language' => $instance->series(WakaTimeSummaryEntry::TYPE_LANGUAGE),
                'editor' => $instance->series(WakaTimeSummaryEntry::TYPE_EDITOR),
                'project' => $instance->series(WakaTimeSummaryEntry::TYPE_PROJECT),
                'category' => $instance->series(WakaTimeSummaryEntry::TYPE_CATEGORY),
                'operating_system' => $instance->series(WakaTimeSummaryEntry::TYPE_OS),
            ],
            'syncedAt' => WakaTimeSummary::query()->max('updated_at') === null
                ? null
                : WakaTimeSummary::query()->latest('updated_at')->first()?->updated_at?->diffForHumans(),
        ];
    }

    protected function rangeFilter(): ?string
    {
        return $this->range;
    }

    /**
     * @return array<int, array{label: string, value: string, caption: string|null, icon: string|null}>
     */
    private function tiles(): array
    {
        $summaries = $this->summaries();
        $total = $this->totalSeconds();
        $days = max(1, $summaries->count());

        $languages = $this->breakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE);
        $topLanguage = array_key_first($languages);

        $tiles = [
            [
                'label' => 'Total coding time',
                'value' => $this->formatDuration($total),
                'caption' => $this->rangeLabel(),
                'icon' => 'pi pi-clock',
            ],
            [
                'label' => 'Daily average',
                'value' => $this->formatDuration((int) round($total / $days)),
                'caption' => 'over '.$days.' '.($days === 1 ? 'day' : 'days').' with data',
                'icon' => 'pi pi-chart-line',
            ],
            [
                'label' => 'Top language',
                'value' => $topLanguage === null ? '—' : (string) $topLanguage,
                'caption' => $topLanguage === null
                    ? 'no entries'
                    : $this->formatDuration($languages[$topLanguage]).' recorded',
                'icon' => 'pi pi-code',
            ],
            [
                'label' => 'Active repositories',
                'value' => (string) Repository::query()->where('is_active', true)->count(),
                'caption' => number_format((int) Repository::query()->sum('commits_count')).' commits recorded',
                'icon' => 'pi pi-github',
            ],
        ];

        $aiCost = $this->grandTotalSum('ai_agent_total_cost');

        if ($aiCost > 0.0) {
            $tiles[] = [
                'label' => 'AI spend',
                'value' => '$'.number_format($aiCost, 2),
                'caption' => $this->formatNumber($this->grandTotalSum('ai_prompt_events_total')).' prompts',
                'icon' => 'pi pi-sparkles',
            ];
        }

        return $tiles;
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>, peak: array{label: string, value: string}|null}
     */
    private function trendSeries(): array
    {
        $labels = [];
        $data = [];

        foreach ($this->summaries() as $summary) {
            $labels[] = $summary->date->format('d M');
            $data[] = (int) $summary->total_seconds;
        }

        $peak = null;

        if ($data !== []) {
            $peakIndex = (int) array_search(max($data), $data, true);
            $peak = [
                'label' => $labels[$peakIndex],
                'value' => $this->formatDuration($data[$peakIndex]),
            ];
        }

        return ['labels' => $labels, 'data' => $data, 'peak' => $peak];
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>, weekendIndexes: array<int, int>, weekdayAverage: string, weekendAverage: string}
     */
    private function weekdaySeries(): array
    {
        /** @var array<int, int> $totals */
        $totals = array_fill(1, 7, 0);
        /** @var array<int, int> $counts */
        $counts = array_fill(1, 7, 0);

        foreach ($this->summaries() as $summary) {
            $day = $summary->date->dayOfWeekIso;
            $totals[$day] += (int) $summary->total_seconds;
            $counts[$day]++;
        }

        $weekdayTotal = array_sum(array_slice($totals, 0, 5, true));
        $weekdayDays = max(1, array_sum(array_slice($counts, 0, 5, true)));
        $weekendTotal = $totals[6] + $totals[7];
        $weekendDays = max(1, $counts[6] + $counts[7]);

        return [
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'data' => array_values($totals),
            'weekendIndexes' => [5, 6],
            'weekdayAverage' => $this->formatDuration((int) round($weekdayTotal / $weekdayDays)),
            'weekendAverage' => $this->formatDuration((int) round($weekendTotal / $weekendDays)),
        ];
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>, durations: array<int, string>, percentages: array<int, float>, total: string}
     */
    private function series(string $type): array
    {
        $seconds = $this->breakdownSeconds($type);
        $total = array_sum($seconds);

        return [
            'labels' => array_map(strval(...), array_keys($seconds)),
            'data' => array_map(intval(...), array_values($seconds)),
            'durations' => array_map($this->formatDuration(...), array_values($seconds)),
            'percentages' => array_map(
                fn (int $value): float => $total > 0 ? round($value / $total * 100, 1) : 0.0,
                array_values($seconds),
            ),
            'total' => $this->formatDuration($total),
        ];
    }
}

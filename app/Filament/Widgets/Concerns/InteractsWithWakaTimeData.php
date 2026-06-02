<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Models\WakaTimeSummary;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Collection;

/**
 * Shared data access for WakaTime widgets. When $record is set (resource view page)
 * the widget reports on that single day; otherwise it aggregates the range chosen in
 * the dashboard's filters form (defaulting to a trailing 7 days).
 */
trait InteractsWithWakaTimeData
{
    use InteractsWithPageFilters;

    public ?WakaTimeSummary $record = null;

    public int $defaultTrailingDays = 7;

    /** Number of trailing days to aggregate, or null for "all time". */
    protected function rangeDays(): ?int
    {
        $range = $this->pageFilters['range'] ?? null;

        if ($range === 'all') {
            return null;
        }

        if (is_int($range) || is_string($range)) {
            return (int) ($range ?: $this->defaultTrailingDays);
        }

        return $this->defaultTrailingDays;
    }

    protected function rangeLabel(): string
    {
        $days = $this->rangeDays();

        return $days ? "Last {$days} days" : 'All time';
    }

    /** @return Collection<int, WakaTimeSummary> */
    protected function summaries(): Collection
    {
        if ($this->record) {
            return collect([$this->record->loadMissing('entries')]);
        }

        $query = WakaTimeSummary::with('entries')->orderBy('date');

        if ($days = $this->rangeDays()) {
            $query->where('date', '>=', now()->subDays($days - 1)->toDateString());
        }

        return $query->get();
    }

    protected function isSingleDay(): bool
    {
        return $this->record !== null;
    }

    /**
     * Sum entry seconds by name for a breakdown type across the active summaries,
     * keeping the top $limit and bucketing the remainder into "Other".
     *
     * @return array<string, int>
     */
    protected function breakdownSeconds(string $type, int $limit = 8): array
    {
        /** @var array<string, int> $totals */
        $totals = [];

        foreach ($this->summaries() as $summary) {
            foreach ($summary->entries->where('type', $type) as $entry) {
                $totals[$entry->name] = ($totals[$entry->name] ?? 0) + (int) $entry->seconds;
            }
        }

        arsort($totals);

        if (count($totals) <= $limit) {
            return $totals;
        }

        $top = array_slice($totals, 0, $limit, true);
        $top['Other'] = (int) array_sum(array_slice($totals, $limit, null, true));

        return $top;
    }

    /** Sum a numeric field from each day's grand_total payload. */
    protected function grandTotalSum(string $key): float
    {
        return (float) $this->summaries()->sum(function (WakaTimeSummary $s) use ($key): float {
            $value = data_get($s->raw, "grand_total.{$key}", 0);

            return is_numeric($value) ? (float) $value : 0.0;
        });
    }

    protected function totalSeconds(): int
    {
        return (int) $this->summaries()->sum(fn (WakaTimeSummary $s): int => $s->total_seconds);
    }

    protected function formatDuration(int|float $seconds): string
    {
        $seconds = (int) $seconds;
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
    }

    protected function formatNumber(float $value): string
    {
        if ($value >= 1_000_000) {
            return round($value / 1_000_000, 1).'M';
        }

        if ($value >= 1_000) {
            return round($value / 1_000, 1).'K';
        }

        return (string) (int) $value;
    }

    /** @return array<int, string> */
    protected function palette(): array
    {
        return [
            '#8b5cf6', // violet
            '#3b82f6', // blue
            '#22c55e', // green
            '#f59e0b', // amber
            '#ef4444', // red
            '#06b6d4', // cyan
            '#ec4899', // pink
            '#84cc16', // lime
            '#a855f7', // purple
            '#64748b', // slate (often "Other")
        ];
    }

    /**
     * Build a Chart.js doughnut dataset (values rendered in hours) from a name=>seconds map.
     *
     * @param  array<string, int>  $secondsByName
     * @return array{datasets: array<int, mixed>, labels: array<int, string>}
     */
    protected function doughnutData(array $secondsByName): array
    {
        $palette = $this->palette();

        $labels = array_map(
            fn (string $name, int $seconds): string => $name.' · '.$this->formatDuration($seconds),
            array_keys($secondsByName),
            array_values($secondsByName),
        );

        return [
            'datasets' => [[
                'data' => array_map(fn (int $s): float => round($s / 3600, 2), array_values($secondsByName)),
                'backgroundColor' => array_slice(array_pad($palette, count($secondsByName), '#64748b'), 0, count($secondsByName)),
                'borderColor' => 'rgba(0,0,0,0)',
            ]],
            'labels' => $labels,
        ];
    }

    /** @return array<string, mixed> */
    protected function doughnutOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'right',
                    'labels' => ['boxWidth' => 12, 'padding' => 12],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}

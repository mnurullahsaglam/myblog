<?php

declare(strict_types=1);

use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;
use App\Support\WakaTime\AggregatesWakaTimeData;

/**
 * Minimal host so the trait can be exercised without a Filament widget.
 */
function wakaTimeAggregator(?string $range = null, ?WakaTimeSummary $record = null): object
{
    return new class($range, $record)
    {
        use AggregatesWakaTimeData {
            breakdownSeconds as public publicBreakdownSeconds;
            totalSeconds as public publicTotalSeconds;
            formatDuration as public publicFormatDuration;
            formatNumber as public publicFormatNumber;
            rangeLabel as public publicRangeLabel;
            grandTotalSum as public publicGrandTotalSum;
        }

        public function __construct(private readonly ?string $range, ?WakaTimeSummary $record)
        {
            $this->record = $record;
        }

        protected function rangeFilter(): ?string
        {
            return $this->range;
        }
    };
}

function makeEntry(WakaTimeSummary $summary, string $type, string $name, int $seconds): WakaTimeSummaryEntry
{
    return WakaTimeSummaryEntry::factory()->create([
        'waka_time_summary_id' => $summary->id,
        'type' => $type,
        'name' => $name,
        'seconds' => $seconds,
    ]);
}

it('sums seconds per name for a breakdown type', function (): void {
    $summary = WakaTimeSummary::factory()->create(['date' => now()->subDay()->toDateString()]);

    makeEntry($summary, WakaTimeSummaryEntry::TYPE_LANGUAGE, 'PHP', 3600);
    makeEntry($summary, WakaTimeSummaryEntry::TYPE_LANGUAGE, 'PHP', 1800);
    makeEntry($summary, WakaTimeSummaryEntry::TYPE_LANGUAGE, 'Vue', 900);

    expect(wakaTimeAggregator()->publicBreakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE))
        ->toBe(['PHP' => 5400, 'Vue' => 900]);
});

it('ignores entries of another type', function (): void {
    $summary = WakaTimeSummary::factory()->create(['date' => now()->toDateString()]);

    makeEntry($summary, WakaTimeSummaryEntry::TYPE_LANGUAGE, 'PHP', 100);
    makeEntry($summary, WakaTimeSummaryEntry::TYPE_EDITOR, 'PhpStorm', 999);

    expect(wakaTimeAggregator()->publicBreakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE))
        ->toBe(['PHP' => 100]);
});

it('excludes summaries outside the trailing range', function (): void {
    $inRange = WakaTimeSummary::factory()->create(['date' => now()->subDays(2)->toDateString()]);
    $outOfRange = WakaTimeSummary::factory()->create(['date' => now()->subDays(40)->toDateString()]);

    makeEntry($inRange, WakaTimeSummaryEntry::TYPE_LANGUAGE, 'PHP', 100);
    makeEntry($outOfRange, WakaTimeSummaryEntry::TYPE_LANGUAGE, 'PHP', 9999);

    expect(wakaTimeAggregator('7')->publicBreakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE))
        ->toBe(['PHP' => 100]);
});

it('includes everything when the range is all', function (): void {
    $old = WakaTimeSummary::factory()->create(['date' => now()->subYears(2)->toDateString()]);

    makeEntry($old, WakaTimeSummaryEntry::TYPE_LANGUAGE, 'PHP', 500);

    expect(wakaTimeAggregator('all')->publicBreakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE))
        ->toBe(['PHP' => 500]);
});

it('reports on a single day when a record is set', function (): void {
    $day = WakaTimeSummary::factory()->create(['date' => now()->subDay()->toDateString()]);
    $otherDay = WakaTimeSummary::factory()->create(['date' => now()->toDateString()]);

    makeEntry($day, WakaTimeSummaryEntry::TYPE_LANGUAGE, 'PHP', 120);
    makeEntry($otherDay, WakaTimeSummaryEntry::TYPE_LANGUAGE, 'PHP', 7777);

    expect(wakaTimeAggregator(null, $day)->publicBreakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE))
        ->toBe(['PHP' => 120]);
});

it('buckets everything past the limit into Other', function (): void {
    $summary = WakaTimeSummary::factory()->create(['date' => now()->toDateString()]);

    foreach (range(1, 12) as $i) {
        makeEntry($summary, WakaTimeSummaryEntry::TYPE_LANGUAGE, "Lang{$i}", $i * 100);
    }

    $result = wakaTimeAggregator()->publicBreakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE);

    expect($result)->toHaveCount(9)
        ->toHaveKey('Other');
    // The four smallest (100 + 200 + 300 + 400) fall into Other.
    expect($result['Other'])->toBe(1000);
});

it('returns an empty breakdown when there is no data', function (): void {
    expect(wakaTimeAggregator()->publicBreakdownSeconds(WakaTimeSummaryEntry::TYPE_LANGUAGE))->toBe([]);
});

it('totals seconds across the range', function (): void {
    WakaTimeSummary::factory()->create(['date' => now()->subDay()->toDateString(), 'total_seconds' => 3600]);
    WakaTimeSummary::factory()->create(['date' => now()->toDateString(), 'total_seconds' => 1800]);

    expect(wakaTimeAggregator()->publicTotalSeconds())->toBe(5400);
});

it('sums a key out of each day raw grand total', function (): void {
    WakaTimeSummary::factory()->create([
        'date' => now()->toDateString(),
        'raw' => ['grand_total' => ['total_seconds' => 120.5]],
    ]);

    expect(wakaTimeAggregator()->publicGrandTotalSum('total_seconds'))->toBe(120.5);
});

it('formats durations', function (int $seconds, string $expected): void {
    expect(wakaTimeAggregator()->publicFormatDuration($seconds))->toBe($expected);
})->with([
    [0, '0m'],
    [59, '0m'],
    [90, '1m'],
    [3600, '1h 0m'],
    [5400, '1h 30m'],
]);

it('formats large numbers compactly', function (float $value, string $expected): void {
    expect(wakaTimeAggregator()->publicFormatNumber($value))->toBe($expected);
})->with([
    [42.0, '42'],
    [1500.0, '1.5K'],
    [2_500_000.0, '2.5M'],
]);

it('labels the range', function (): void {
    expect(wakaTimeAggregator('30')->publicRangeLabel())->toBe('Last 30 days')
        ->and(wakaTimeAggregator('all')->publicRangeLabel())->toBe('All time')
        ->and(wakaTimeAggregator()->publicRangeLabel())->toBe('Last 7 days');
});

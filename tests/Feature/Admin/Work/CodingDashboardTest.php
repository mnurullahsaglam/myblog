<?php

declare(strict_types=1);

use App\Models\Repository;
use App\Models\User;
use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;
use Inertia\Testing\AssertableInertia;

beforeEach(function (): void {
    config(['app.admin_email' => 'admin@example.test']);
    $this->actingAs(User::factory()->create(['email' => 'admin@example.test']));
});

function seedDay(string $date, int $seconds, array $entries = []): WakaTimeSummary
{
    $summary = WakaTimeSummary::factory()->create(['date' => $date, 'total_seconds' => $seconds]);

    foreach ($entries as [$type, $name, $entrySeconds]) {
        WakaTimeSummaryEntry::factory()->create([
            'waka_time_summary_id' => $summary->id,
            'type' => $type,
            'name' => $name,
            'seconds' => $entrySeconds,
        ]);
    }

    return $summary;
}

it('renders every series the dashboard needs', function (): void {
    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Work/CodingDashboard')
            ->where('data.range', '7')
            ->has('data.tiles')
            ->has('data.trend.labels')
            ->has('data.weekday.labels', 7)
            ->has('data.breakdowns.language')
            ->has('data.breakdowns.editor')
            ->has('data.breakdowns.project')
            ->has('data.breakdowns.category')
            ->has('data.breakdowns.operating_system')
            ->has('ranges', 5)
        );
});

it('honours the range and falls back for an unknown one', function (): void {
    $this->get(route('admin.coding-dashboard', ['range' => '30']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.range', '30')
            ->where('data.rangeLabel', 'Last 30 days')
        );

    $this->get(route('admin.coding-dashboard', ['range' => 'forever']))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('data.range', '7'));
});

it('aggregates a language breakdown with durations and percentages', function (): void {
    seedDay(now()->subDay()->toDateString(), 10800, [
        [WakaTimeSummaryEntry::TYPE_LANGUAGE, 'PHP', 7200],
        [WakaTimeSummaryEntry::TYPE_LANGUAGE, 'Vue', 3600],
    ]);

    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.breakdowns.language.labels.0', 'PHP')
            ->where('data.breakdowns.language.data.0', 7200)
            ->where('data.breakdowns.language.durations.0', '2h 0m')
            ->where('data.breakdowns.language.percentages.0', 66.7)
            ->where('data.breakdowns.language.total', '3h 0m')
        );
});

it('reports total time and daily average as durations', function (): void {
    seedDay(now()->subDay()->toDateString(), 7200);
    seedDay(now()->toDateString(), 3600);

    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $tiles = collect($page->toArray()['props']['data']['tiles'])->keyBy('label');

            expect($tiles['Total coding time']['value'])->toBe('3h 0m');
            expect($tiles['Daily average']['value'])->toBe('1h 30m');
        });
});

it('names the top language in a tile', function (): void {
    seedDay(now()->toDateString(), 3600, [
        [WakaTimeSummaryEntry::TYPE_LANGUAGE, 'Rust', 3600],
    ]);

    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $tiles = collect($page->toArray()['props']['data']['tiles'])->keyBy('label');

            expect($tiles['Top language']['value'])->toBe('Rust');
        });
});

it('counts active repositories and their commits', function (): void {
    Repository::factory()->create(['is_active' => true, 'commits_count' => 300]);
    Repository::factory()->create(['is_active' => true, 'commits_count' => 84]);
    Repository::factory()->create(['is_active' => false, 'commits_count' => 1000]);

    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $tiles = collect($page->toArray()['props']['data']['tiles'])->keyBy('label');

            expect($tiles['Active repositories']['value'])->toBe('2');
            expect($tiles['Active repositories']['caption'])->toContain('1,384 commits');
        });
});

it('marks the peak day on the trend', function (): void {
    seedDay(now()->subDays(2)->toDateString(), 3600);
    seedDay(now()->subDay()->toDateString(), 10800);

    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.trend.peak.value', '3h 0m')
        );
});

it('splits weekday and weekend averages', function (): void {
    // A Monday and a Saturday.
    seedDay('2026-06-01', 7200);
    seedDay('2026-06-06', 1800);

    $this->get(route('admin.coding-dashboard', ['range' => 'all']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.weekday.weekdayAverage', '2h 0m')
            ->where('data.weekday.weekendAverage', '30m')
            ->where('data.weekday.weekendIndexes', [5, 6])
        );
});

it('renders empty series rather than failing when there is no data', function (): void {
    $this->get(route('admin.coding-dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.breakdowns.language.labels', [])
            ->where('data.trend.peak', null)
            ->where('data.syncedAt', null)
        );
});

it('omits the AI tile when nothing was spent', function (): void {
    seedDay(now()->toDateString(), 3600);

    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $labels = collect($page->toArray()['props']['data']['tiles'])->pluck('label');

            expect($labels)->not->toContain('AI spend');
        });
});

it('shows the AI tile when wakatime reported spend', function (): void {
    WakaTimeSummary::factory()->create([
        'date' => now()->toDateString(),
        'total_seconds' => 3600,
        'raw' => ['grand_total' => ['ai_agent_total_cost' => 4.2, 'ai_prompt_events_total' => 120]],
    ]);

    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(function (AssertableInertia $page): void {
            $tiles = collect($page->toArray()['props']['data']['tiles'])->keyBy('label');

            expect($tiles)->toHaveKey('AI spend');
            expect($tiles['AI spend']['value'])->toBe('$4.20');
            expect($tiles['AI spend']['caption'])->toBe('120 prompts');
        });
});

it('reports when the data was last synced', function (): void {
    seedDay(now()->toDateString(), 3600);

    $this->get(route('admin.coding-dashboard'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('data.syncedAt', fn (?string $value): bool => is_string($value) && $value !== '')
        );
});

it('appears in the work cluster', function (): void {
    $work = collect(App\Support\Navigation::clusters())->firstWhere('label', 'Work');

    expect(collect($work['items'])->pluck('route'))->toContain('admin.coding-dashboard');
});

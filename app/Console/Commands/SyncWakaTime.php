<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;
use App\Services\WakaTimeService;
use Filament\Notifications\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SyncWakaTime extends Command
{
    /**
     * Maps the WakaTime summary payload keys to our entry "type" values.
     *
     * @var array<string, string>
     */
    private const array BREAKDOWNS = [
        'projects' => WakaTimeSummaryEntry::TYPE_PROJECT,
        'languages' => WakaTimeSummaryEntry::TYPE_LANGUAGE,
        'editors' => WakaTimeSummaryEntry::TYPE_EDITOR,
        'operating_systems' => WakaTimeSummaryEntry::TYPE_OS,
        'categories' => WakaTimeSummaryEntry::TYPE_CATEGORY,
    ];
    protected $signature = 'wakatime:sync
                            {--days=7 : Number of trailing days to fetch and upsert (max ~14 on the free plan)}';
    protected $description = 'Fetch WakaTime daily summaries and upsert them (totals + project/language/editor/os/category breakdowns)';

    public function handle(WakaTimeService $wakatime): int
    {
        $days = max(1, (int)$this->option('days'));
        $end = now();
        $start = now()->subDays($days - 1);

        $this->info("Syncing WakaTime summaries from {$start->toDateString()} to {$end->toDateString()}...");

        try {
            $summaries = $wakatime->fetchSummaries($start, $end);
        } catch (\Throwable $e) {
            return $this->reportFailure($e);
        }

        $count = 0;

        foreach ($summaries as $day) {
            $date = data_get($day, 'range.date');

            if (!$date) {
                continue;
            }

            try {
                $this->upsertDay($day, $date);
                $count++;
            } catch (\Throwable $e) {
                return $this->reportFailure($e);
            }
        }

        $this->info("✅ Synced {$count} day(s) of WakaTime data.");

        return self::SUCCESS;
    }

    private function reportFailure(\Throwable $e): int
    {
        Log::error('WakaTime sync failed: ' . $e->getMessage(), ['exception' => $e]);

        $this->error('❌ ' . $e->getMessage());

        $this->notifyAdmin($e->getMessage());

        return self::FAILURE;
    }

    private function notifyAdmin(string $message): void
    {
        $admin = User::where('email', config('app.admin_email'))->first();

        if (!$admin) {
            return;
        }

        Notification::make()
            ->title('WakaTime sync failed')
            ->body($message . ' You may need to reconnect WakaTime in the admin panel.')
            ->danger()
            ->sendToDatabase($admin);
    }

    /**
     * @param array<string, mixed> $day
     * @throws \Throwable
     */
    private function upsertDay(array $day, string $date): void
    {
        DB::transaction(function () use ($day, $date): void {
            $summary = WakaTimeSummary::updateOrCreate(
                ['date' => $date],
                [
                    'total_seconds' => (int)data_get($day, 'grand_total.total_seconds', 0),
                    'raw' => $day,
                ],
            );

            // Idempotent: rebuild child entries from scratch each run.
            $summary->entries()->delete();

            $rows = [];

            foreach (self::BREAKDOWNS as $payloadKey => $type) {
                foreach (data_get($day, $payloadKey, []) as $item) {
                    $name = data_get($item, 'name');

                    if ($name === null || $name === '') {
                        continue;
                    }

                    $rows[] = [
                        'waka_time_summary_id' => $summary->id,
                        'type' => $type,
                        'name' => $name,
                        'seconds' => (int)data_get($item, 'total_seconds', 0),
                        'percent' => (float)data_get($item, 'percent', 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($rows !== []) {
                WakaTimeSummaryEntry::insert($rows);
            }

            $this->line("  • {$date}: " . $summary->total_human);
        });
    }
}

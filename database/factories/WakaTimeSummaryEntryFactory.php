<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WakaTimeSummary;
use App\Models\WakaTimeSummaryEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WakaTimeSummaryEntry>
 */
class WakaTimeSummaryEntryFactory extends Factory
{
    protected $model = WakaTimeSummaryEntry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'waka_time_summary_id' => WakaTimeSummary::factory(),
            'type' => fake()->randomElement([
                WakaTimeSummaryEntry::TYPE_PROJECT,
                WakaTimeSummaryEntry::TYPE_LANGUAGE,
                WakaTimeSummaryEntry::TYPE_EDITOR,
                WakaTimeSummaryEntry::TYPE_OS,
                WakaTimeSummaryEntry::TYPE_CATEGORY,
            ]),
            'name' => fake()->word(),
            'seconds' => fake()->numberBetween(60, 14400),
            'percent' => fake()->randomFloat(2, 0, 100),
        ];
    }
}

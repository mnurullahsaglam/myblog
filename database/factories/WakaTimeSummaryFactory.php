<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WakaTimeSummary;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<WakaTimeSummary>
 */
class WakaTimeSummaryFactory extends Factory
{
    #[Override]
    protected $model = WakaTimeSummary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->unique()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'total_seconds' => fake()->numberBetween(0, 36000),
            'raw' => null,
        ];
    }
}

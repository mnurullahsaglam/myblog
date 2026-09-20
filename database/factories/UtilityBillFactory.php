<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UtilityAccount;
use App\Models\UtilityBill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UtilityBill>
 */
final class UtilityBillFactory extends Factory
{
    protected $model = UtilityBill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'utility_account_id' => UtilityAccount::factory(),
            'expense_id' => null,
            'bill_number' => fake()->numerify('FTR-#######'),
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'issued_at' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'meter_start' => null,
            'meter_end' => null,
            'total_amount' => 250.00,
            'currency' => 'TRY',
            'paid_at' => null,
            'document_path' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => ['paid_at' => now()]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'due_date' => now()->subDays(3)->toDateString(),
            'paid_at' => null,
        ]);
    }
}

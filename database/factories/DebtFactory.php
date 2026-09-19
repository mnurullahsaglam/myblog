<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currencies;
use App\Models\Debt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
{
    #[Override]
    protected $model = Debt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currencies = Currencies::cases();

        return [
            'creditor_name' => fake()->name(),
            'creditor_type' => fake()->randomElement(['person', 'institute']),
            'amount' => fake()->randomFloat(2, 100, 20000),
            'currency' => $currencies[random_int(0, count($currencies) - 1)]->value,
            'status' => 'pending',
            'date' => fake()->dateTimeBetween('-6 months')->format('Y-m-d'),
            'due_date' => fake()->dateTimeBetween('now', '+6 months')->format('Y-m-d'),
            'description' => fake()->sentence(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => 'paid']);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'pending',
            'due_date' => now()->subDays(fake()->numberBetween(1, 60))->format('Y-m-d'),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currencies;
use App\Models\Debt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debt>
 */
class DebtFactory extends Factory
{
    protected $model = Debt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creditor_name' => fake()->name(),
            'creditor_type' => fake()->randomElement(['person', 'institute']),
            'amount' => fake()->randomFloat(2, 100, 20000),
            'currency' => Currencies::cases()[array_rand(Currencies::cases())]->value,
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

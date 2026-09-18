<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currencies;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'debt_id' => null,
            'amount' => fake()->randomFloat(2, 10, 5000),
            'currency' => fake()->randomElement(Currencies::cases())->value,
            'description' => fake()->sentence(),
            'receipt_path' => null,
            'date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
        ];
    }

    public function withReceipt(): static
    {
        return $this->state(fn (array $attributes): array => [
            'receipt_path' => 'receipts/'.fake()->uuid().'.png',
        ]);
    }
}

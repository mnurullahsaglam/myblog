<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currencies;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    #[Override]
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
            'currency' => Currencies::cases()[array_rand(Currencies::cases())]->value,
            'description' => fake()->sentence(),
            'is_recurring' => false,
            'is_tax_deductible' => false,
            'receipt_path' => null,
            'date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
        ];
    }

    public function recurring(): static
    {
        return $this->state(fn (array $attributes): array => ['is_recurring' => true]);
    }

    public function taxDeductible(): static
    {
        return $this->state(fn (array $attributes): array => ['is_tax_deductible' => true]);
    }

    public function withReceipt(): static
    {
        return $this->state(fn (array $attributes): array => [
            'receipt_path' => 'receipts/'.fake()->uuid().'.png',
        ]);
    }
}

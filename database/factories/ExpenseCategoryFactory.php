<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    #[Override]
    protected $model = ExpenseCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'color' => fake()->hexColor(),
        ];
    }

    public function withoutColor(): static
    {
        return $this->state(fn (array $attributes): array => ['color' => null]);
    }
}

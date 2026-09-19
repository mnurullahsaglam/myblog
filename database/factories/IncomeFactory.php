<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currencies;
use App\Models\Income;
use App\Models\IncomeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Income>
 */
class IncomeFactory extends Factory
{
    #[Override]
    protected $model = Income::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currencies = Currencies::cases();

        return [
            'client_id' => null,
            'income_category_id' => IncomeCategory::factory(),
            'invoice_id' => null,
            'debt_id' => null,
            'amount' => fake()->randomFloat(2, 100, 50000),
            'currency' => $currencies[random_int(0, count($currencies) - 1)]->value,
            'description' => fake()->sentence(),
            'date' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UtilityBill;
use App\Models\UtilityBillLine;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<UtilityBillLine>
 */
final class UtilityBillLineFactory extends Factory
{
    #[Override]
    protected $model = UtilityBillLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $labels = ['Enerji bedeli', 'Dağıtım bedeli', 'KDV', 'ÖİV', 'Atıksu bedeli'];

        return [
            'utility_bill_id' => UtilityBill::factory(),
            'label' => $labels[random_int(0, count($labels) - 1)],
            'amount' => 10.00,
            'sort_order' => 0,
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currencies;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(1000, 100000);
        $rates = [0, 10, 18, 20];
        $taxRate = $rates[array_rand($rates)];
        $taxAmount = (int) round($amount * $taxRate / 100);

        return [
            'client_id' => Client::factory(),
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'issued_at' => fake()->dateTimeBetween('-1 year')->format('Y-m-d'),
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'amount' => $amount,
            'total_amount' => $amount + $taxAmount,
            'currency' => Currencies::cases()[array_rand(Currencies::cases())]->value,
            'invoice' => 'invoices/'.fake()->uuid().'.zip',
            'invoice_pdf' => null,
        ];
    }
}

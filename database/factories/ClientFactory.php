<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'country' => fake()->country(),
            'address' => fake()->address(),
            'tax_no' => fake()->numerify('##########'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Override;

/**
 * @extends Factory<Publisher>
 */
class PublisherFactory extends Factory
{
    #[Override]
    protected $model = Publisher::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'slug' => fake()->slug(),
            'image' => fake()->word(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ];
    }
}

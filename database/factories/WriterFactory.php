<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Writer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Override;

/**
 * @extends Factory<Writer>
 */
class WriterFactory extends Factory
{
    #[Override]
    protected $model = Writer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'slug' => fake()->slug(),
            'bio' => fake()->word(),
            'birth_place' => fake()->word(),
            'death_place' => fake()->word(),
            'birth_year' => fake()->randomNumber(),
            'death_year' => fake()->randomNumber(),
            'image' => fake()->word(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ];
    }
}

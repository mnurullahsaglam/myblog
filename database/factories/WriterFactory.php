<?php

namespace Database\Factories;

use App\Models\Writer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class WriterFactory extends Factory
{
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
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Publisher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class PublisherFactory extends Factory
{
    protected $model = Publisher::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'slug' => fake()->slug(),
            'image' => fake()->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}

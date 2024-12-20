<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\Publisher;
use App\Models\Writer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class BookFactory extends Factory
{
    protected $model = Book::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'original_name' => fake()->name(),
            'slug' => fake()->slug(),
            'page_count' => fake()->randomNumber(),
            'publication_date' => fake()->randomNumber(),
            'publication_location' => fake()->word(),
            'edition_number' => fake()->randomNumber(),
            'image' => fake()->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'writer_id' => Writer::factory(),
            'publisher_id' => Publisher::factory(),
        ];
    }
}

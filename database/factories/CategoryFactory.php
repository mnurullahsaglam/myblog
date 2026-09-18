<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Categories are polymorphic children, so one always belongs to something.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'categoriable_type' => Post::class,
            'categoriable_id' => Post::factory(),
        ];
    }

    public function for_(Model $model): static
    {
        return $this->state(fn (array $attributes): array => [
            'categoriable_type' => $model::class,
            'categoriable_id' => $model->getKey(),
        ]);
    }
}

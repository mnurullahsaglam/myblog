<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Override;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    #[Override]
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'title' => fake()->word(),
            'slug' => fake()->slug(),
            'content' => fake()->word(),
            'image' => fake()->word(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ];
    }
}

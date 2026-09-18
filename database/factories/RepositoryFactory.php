<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Repository;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Repository>
 */
class RepositoryFactory extends Factory
{
    protected $model = Repository::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $owner = fake()->userName();
        $name = fake()->unique()->slug(2);

        return [
            'project_id' => Project::factory(),
            'name' => $name,
            'full_name' => $owner.'/'.$name,
            'owner' => $owner,
            'description' => fake()->sentence(),
            'visibility' => fake()->randomElement(['public', 'private']),
            'github_url' => 'https://github.com/'.$owner.'/'.$name,
            'github_id' => fake()->unique()->numerify('########'),
            'default_branch' => 'main',
            'language' => fake()->randomElement(['PHP', 'Vue', 'Rust', 'Go', 'TypeScript']),
            'stars_count' => fake()->numberBetween(0, 500),
            'forks_count' => fake()->numberBetween(0, 100),
            'issues_count' => fake()->numberBetween(0, 50),
            'is_active' => true,
            'github_created_at' => fake()->dateTimeBetween('-3 years'),
            'github_updated_at' => fake()->dateTimeBetween('-1 month'),
            'last_synced_at' => fake()->dateTimeBetween('-1 week'),
        ];
    }
}

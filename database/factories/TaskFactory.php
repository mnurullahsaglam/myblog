<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    #[Override]
    protected $model = Task::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'repository_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => fake()->randomElement(['todo', 'in_progress', 'completed']),
            'sort_order' => null,
        ];
    }

    public function githubIssue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'github_issue_number' => (string) fake()->unique()->numberBetween(1, 5000),
            'github_issue_url' => 'https://github.com/owner/repo/issues/'.fake()->numberBetween(1, 5000),
            'github_issue_state' => 'open',
            'github_issue_labels' => [['name' => 'bug', 'color' => 'd73a4a']],
            'github_assignee' => fake()->userName(),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Override;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    #[Override]
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'due_date' => Date::now(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),

            'client_id' => Client::factory(),
        ];
    }
}

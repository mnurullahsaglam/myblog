<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group' => fake()->randomElement(['site_info', 'meta', 'branding', 'appearance']),
            'name' => fake()->unique()->slug(2),
            'value' => fake()->word(),
            'type' => 'text',
        ];
    }
}

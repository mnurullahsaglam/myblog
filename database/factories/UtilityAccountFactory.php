<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UtilityType;
use App\Models\UtilityAccount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Override;

/**
 * @extends Factory<UtilityAccount>
 */
final class UtilityAccountFactory extends Factory
{
    #[Override]
    protected $model = UtilityAccount::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = UtilityType::cases();
        $type = $types[random_int(0, count($types) - 1)];

        return [
            'type' => $type->value,
            'provider' => fake()->company(),
            'subscriber_no' => fake()->numerify('##########'),
            'label' => $type->getLabel().' — '.fake()->word(),
            'is_active' => true,
        ];
    }

    public function metered(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => UtilityType::Electricity->value]);
    }

    public function unmetered(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => UtilityType::Internet->value]);
    }
}

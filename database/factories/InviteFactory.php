<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Invite;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invite>
 */
class InviteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Member->value,
            'token_hash' => hash('sha256', Str::random(64)),
            'expires_at' => now()->addHours(48),
            'accepted_at' => null,
            'revoked_at' => null,
            'invited_by' => null,
        ];
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes): array => ['accepted_at' => now()]);
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => ['revoked_at' => now()]);
    }

    /** Past its window, whatever else is true of it. */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => ['expires_at' => now()->subMinute()]);
    }
}

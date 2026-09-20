<?php

declare(strict_types=1);

namespace App\Support\Access;

use App\Enums\Ability;
use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\User;
use InvalidArgumentException;

/**
 * Everything the panel is allowed to show this request, in one place.
 *
 * Areas, abilities and feature flags are asked of this object rather than of the
 * user, so that view-as can substitute all three at once. A preview that swapped
 * only some of them would report a screen as hidden while its route stayed open,
 * which is worse than having no preview at all.
 */
final class AccessProfile
{
    /**
     * @param  array<int, Area>  $areas
     * @param  array<int, Ability>  $abilities
     */
    private function __construct(
        private readonly ?User $user,
        private readonly array $areas,
        private readonly array $abilities,
        private readonly bool $previewing,
    ) {}

    public static function forUser(?User $user): self
    {
        if (! $user instanceof User) {
            return new self(null, [], [], false);
        }

        return new self($user, $user->areas(), $user->abilities(), false);
    }

    /**
     * Render as a narrower role would see it.
     *
     * The role must grant strictly less than the viewer already has, so this can
     * only ever be used to look down.
     */
    public static function preview(User $user, UserRole $role): self
    {
        $areas = $role->areas();

        if (count($areas) >= count($user->areas())) {
            throw new InvalidArgumentException('A preview may only narrow what is visible.');
        }

        return new self($user, $areas, $role->abilities(), true);
    }

    /**
     * @return array<int, Area>
     */
    public function areas(): array
    {
        return $this->areas;
    }

    public function canAccess(Area $area): bool
    {
        return in_array($area, $this->areas, true);
    }

    public function allows(Ability $ability): bool
    {
        return in_array($ability, $this->abilities, true);
    }

    public function isPreviewing(): bool
    {
        return $this->previewing;
    }

    public function user(): ?User
    {
        return $this->user;
    }
}

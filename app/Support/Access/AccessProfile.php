<?php

declare(strict_types=1);

namespace App\Support\Access;

use App\Enums\Ability;
use App\Enums\Area;
use App\Enums\UserRole;
use App\Models\User;
use App\Support\Features;
use InvalidArgumentException;
use Laravel\Pennant\Feature;

/**
 * Everything the panel is allowed to show this request, in one place.
 *
 * Areas, abilities and feature flags are asked of this object rather than of the
 * user, so that view-as can substitute all three at once. A preview that swapped
 * only some of them would report a screen as hidden while its route stayed open,
 * which is worse than having no preview at all.
 */
final readonly class AccessProfile
{
    /**
     * @param  array<int, Area>  $areas
     * @param  array<int, Ability>  $abilities
     * @param  array<string, bool>  $flags
     */
    private function __construct(
        private ?User $user,
        private array $areas,
        private array $abilities,
        private bool $previewing,
        private bool $privileged,
        private array $flags,
    ) {}

    public static function forUser(?User $user): self
    {
        if (! $user instanceof User) {
            return new self(null, [], [], false, false, []);
        }

        $privileged = $user->abilities() !== [];

        return new self(
            $user,
            $user->areas(),
            $user->abilities(),
            false,
            $privileged,
            $privileged ? [] : self::resolveFlags($user),
        );
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

        throw_if(count($areas) >= count($user->areas()), InvalidArgumentException::class, 'A preview may only narrow what is visible.');

        return new self($user, $areas, $role->abilities(), true, false, self::resolveFlags($user));
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

    /**
     * An admin sees every flag. A member sees one only once it is turned on for
     * her, and a flag nobody declared is always off.
     */
    public function feature(string $flag): bool
    {
        if (! in_array($flag, Features::ALL, true)) {
            return false;
        }

        if ($this->privileged) {
            return true;
        }

        return $this->flags[$flag] ?? false;
    }

    /**
     * Resolved once per profile, so a page that checks several flags makes one
     * round trip rather than one per check.
     *
     * @return array<string, bool>
     */
    private static function resolveFlags(User $user): array
    {
        $flags = [];

        foreach (Features::ALL as $flag) {
            $flags[$flag] = Feature::for($user)->active($flag);
        }

        return $flags;
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

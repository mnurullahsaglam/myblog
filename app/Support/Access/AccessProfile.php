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

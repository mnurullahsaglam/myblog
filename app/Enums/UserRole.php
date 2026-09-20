<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Member = 'member';

    /**
     * @return array<int, Ability>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::Admin => Ability::cases(),
            self::Member => [],
        };
    }

    /**
     * @return array<int, Area>
     */
    public function areas(): array
    {
        return match ($this) {
            self::Admin => Area::cases(),
            self::Member => [Area::Budget, Area::Utilities, Area::Library],
        };
    }
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * users.role is a plain string column cast to this enum, not a database enum,
 * so adding a role is one line here and no migration.
 *
 * The enum implements no presentation contracts because no screen lists users
 * yet; HasColor and HasLabel can be added the day one does.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Member = 'member';

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

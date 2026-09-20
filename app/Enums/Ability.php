<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * A named capability, mirroring Area.
 *
 * Named for what it permits rather than who is excluded, so adding a second
 * member later needs no change to any field that references one.
 */
enum Ability: string
{
    case SeeClientIdentity = 'see-client-identity';
}

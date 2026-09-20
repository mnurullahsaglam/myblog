<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The parts of the panel access is granted over.
 *
 * These match the navigation clusters deliberately: it is the grouping the panel
 * already presents and the one both users already think in.
 */
enum Area: string
{
    case Blog = 'blog';
    case Budget = 'budget';
    case Work = 'work';
    case Library = 'library';
    case Utilities = 'utilities';
    case General = 'general';
}

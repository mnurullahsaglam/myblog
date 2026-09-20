<?php

declare(strict_types=1);

namespace App\Enums;

enum Area: string
{
    case Blog = 'blog';
    case Budget = 'budget';
    case Work = 'work';
    case Library = 'library';
    case Utilities = 'utilities';
    case General = 'general';
}

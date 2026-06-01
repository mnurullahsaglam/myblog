<?php

declare(strict_types=1);

namespace App\Traits;

trait SlugAsRouteKeyName
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

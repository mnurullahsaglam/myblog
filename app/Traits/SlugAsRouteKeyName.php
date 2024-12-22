<?php

namespace App\Traits;

trait SlugAsRouteKeyName
{
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

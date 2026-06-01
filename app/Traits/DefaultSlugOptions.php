<?php

declare(strict_types=1);

namespace App\Traits;

use Spatie\Sluggable\SlugOptions;

trait DefaultSlugOptions
{
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\DefaultSlugOptions;
use App\Traits\SlugAsRouteKeyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;

class Publisher extends Model
{
    use DefaultSlugOptions, HasFactory, HasSlug, SlugAsRouteKeyName;

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}

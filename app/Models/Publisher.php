<?php

namespace App\Models;

use App\Traits\DefaultSlugOptions;
use App\Traits\SlugAsRouteKeyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;

class Publisher extends Model
{
    use HasFactory, HasSlug, SlugAsRouteKeyName, DefaultSlugOptions;

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}

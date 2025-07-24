<?php

namespace App\Models;

use App\Traits\DefaultSlugOptions;
use App\Traits\SlugAsRouteKeyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Sluggable\HasSlug;

class Category extends Model
{
    use HasFactory, HasSlug, SlugAsRouteKeyName, DefaultSlugOptions;

    public function categoriable(): MorphTo
    {
        return $this->morphTo();
    }
}

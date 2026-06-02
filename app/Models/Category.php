<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\DefaultSlugOptions;
use App\Traits\SlugAsRouteKeyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Sluggable\HasSlug;

class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use DefaultSlugOptions, HasFactory, HasSlug, SlugAsRouteKeyName;

    /**
     * @return MorphTo<Model, $this>
     */
    public function categoriable(): MorphTo
    {
        return $this->morphTo();
    }
}

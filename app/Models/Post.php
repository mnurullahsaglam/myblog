<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\CategoriableRelation;
use App\Traits\SlugAsRouteKeyName;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Post extends Model
{
    use CategoriableRelation;

    /** @use HasFactory<PostFactory> */
    use HasFactory;

    use HasSlug;
    use SlugAsRouteKeyName;

    /**
     * Posts have a title, not a name, so the default options do not apply.
     */
    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }
}

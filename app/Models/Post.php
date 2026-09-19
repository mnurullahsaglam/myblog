<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\CategoriableRelation;
use App\Traits\SlugAsRouteKeyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use CategoriableRelation, HasFactory, HasSlug, SlugAsRouteKeyName;

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

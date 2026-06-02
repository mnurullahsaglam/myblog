<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\CategoriableRelation;
use App\Traits\DefaultSlugOptions;
use App\Traits\SlugAsRouteKeyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;

class Post extends Model
{
    /** @use HasFactory<\Database\Factories\PostFactory> */
    use CategoriableRelation, DefaultSlugOptions, HasFactory, HasSlug, SlugAsRouteKeyName;
}

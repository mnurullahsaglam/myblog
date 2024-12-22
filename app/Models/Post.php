<?php

namespace App\Models;

use App\Traits\CategoriableRelation;
use App\Traits\DefaultSlugOptions;
use App\Traits\SlugAsRouteKeyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;

class Post extends Model
{
    use HasFactory, HasSlug, SlugAsRouteKeyName, DefaultSlugOptions, CategoriableRelation;

    protected $fillable = [
        'title',
        'slug',
        'content',
        'image',
    ];
}

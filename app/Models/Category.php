<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\DefaultSlugOptions;
use App\Traits\SlugAsRouteKeyName;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Spatie\Sluggable\HasSlug;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use DefaultSlugOptions, HasFactory, HasSlug, SlugAsRouteKeyName;

    /**
     * @return MorphToMany<Post, $this>
     */
    public function posts(): MorphToMany
    {
        return $this->morphedByMany(Post::class, 'categoriable');
    }

    /**
     * @return MorphToMany<Book, $this>
     */
    public function books(): MorphToMany
    {
        return $this->morphedByMany(Book::class, 'categoriable');
    }
}

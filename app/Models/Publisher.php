<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\DefaultSlugOptions;
use App\Traits\SlugAsRouteKeyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;

/**
 * @property string $name
 */
class Publisher extends Model
{
    /** @use HasFactory<\Database\Factories\PublisherFactory> */
    use DefaultSlugOptions, HasFactory, HasSlug, SlugAsRouteKeyName;

    /**
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}

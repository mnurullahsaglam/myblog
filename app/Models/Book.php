<?php

namespace App\Models;

use App\Traits\CategoriableRelation;
use App\Traits\DefaultSlugOptions;
use App\Traits\ImageUrlAttribute;
use App\Traits\SlugAsRouteKeyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;

class Book extends Model
{
    use HasFactory, HasSlug, SlugAsRouteKeyName, DefaultSlugOptions, CategoriableRelation, ImageUrlAttribute;

    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    protected function casts(): array
    {
        return [
            'publication_date' => 'integer',
        ];
    }
}

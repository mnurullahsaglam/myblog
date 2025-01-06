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

    protected $fillable = [
        'writer_id',
        'publisher_id',
        'name',
        'original_name',
        'slug',
        'page_count',
        'publication_date',
        'publication_location',
        'edition_number',
        'image',
    ];

    protected function casts(): array
    {
        return [
            'publication_date' => 'integer',
        ];
    }

    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }
}

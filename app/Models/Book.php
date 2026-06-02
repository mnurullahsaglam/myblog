<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\CategoriableRelation;
use App\Traits\DefaultSlugOptions;
use App\Traits\ImageUrlAttribute;
use App\Traits\SlugAsRouteKeyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Sluggable\HasSlug;

/**
 * @property string|null $image
 * @property-read Writer|null $writer
 * @property-read Publisher|null $publisher
 */
class Book extends Model
{
    /** @use HasFactory<\Database\Factories\BookFactory> */
    use CategoriableRelation, DefaultSlugOptions, HasFactory, HasSlug, ImageUrlAttribute, SlugAsRouteKeyName;

    /**
     * @return BelongsTo<Writer, $this>
     */
    public function writer(): BelongsTo
    {
        return $this->belongsTo(Writer::class);
    }

    /**
     * @return BelongsTo<Publisher, $this>
     */
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

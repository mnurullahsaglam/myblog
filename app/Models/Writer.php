<?php

namespace App\Models;

use App\Traits\DefaultSlugOptions;
use App\Traits\SlugAsRouteKeyName;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;

class Writer extends Model
{
    use HasFactory, HasSlug, SlugAsRouteKeyName, DefaultSlugOptions;

    protected $fillable = [
        'name',
        'slug',
        'bio',
        'birth_place',
        'death_place',
        'birth_year',
        'death_year',
        'image',
    ];

    protected function casts(): array
    {
        return [
            'birth_year' => 'integer',
            'death_year' => 'integer',
        ];
    }

    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }
}

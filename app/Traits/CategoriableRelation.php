<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Category;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait CategoriableRelation
{
    public function categoriable(): MorphMany
    {
        return $this->morphMany(Category::class, 'categoriable');
    }
}

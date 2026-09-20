<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Category;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait CategoriableRelation
{
    public static function bootCategoriableRelation(): void
    {
        static::deleting(function (self $model): void {
            $model->categories()->detach();
        });
    }

    /**
     * @return MorphToMany<Category, $this>
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(Category::class, 'categoriable');
    }
}

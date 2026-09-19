<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Category;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait CategoriableRelation
{
    /**
     * Detach the taxonomy before the record goes.
     *
     * The categoriables pivot cascades on category_id, but the polymorphic side
     * cannot carry a foreign key, so deleting a post or a book would otherwise
     * leave its pivot rows behind pointing at nothing.
     */
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

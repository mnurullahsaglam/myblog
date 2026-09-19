<?php

declare(strict_types=1);

namespace App\Actions\Resources\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait SyncsRelations
{
    /**
     * @param  array<string, array<int, mixed>>  $relations
     */
    private function syncRelations(Model $record, array $relations): void
    {
        foreach ($relations as $key => $ids) {
            if (! method_exists($record, $key)) {
                continue;
            }

            $relation = $record->{$key}();

            if ($relation instanceof BelongsToMany) {
                $relation->sync($ids);
            }
        }
    }
}

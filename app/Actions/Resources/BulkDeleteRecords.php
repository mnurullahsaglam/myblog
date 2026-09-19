<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Delete several records of one type, reporting how many rows actually went.
 *
 * Deletes row by row rather than with a single mass query, because a mass
 * delete fires no model events: models that detach a polymorphic pivot on
 * deleting would leave their pivot rows behind. One transaction keeps it atomic.
 *
 * The caller needs the real count rather than the requested count, because the
 * success message names a number and ids can disappear between render and submit.
 */
final class BulkDeleteRecords
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int>  $ids
     */
    public function handle(string $modelClass, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($modelClass, $ids): int {
            $records = $modelClass::query()->whereKey($ids)->get();

            foreach ($records as $record) {
                $record->delete();
            }

            return $records->count();
        });
    }
}

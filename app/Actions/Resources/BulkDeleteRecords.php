<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use Illuminate\Database\Eloquent\Model;

/**
 * Delete several records of one type, reporting how many rows actually went.
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

        $deleted = $modelClass::query()->whereKey($ids)->delete();

        return is_int($deleted) ? $deleted : 0;
    }
}

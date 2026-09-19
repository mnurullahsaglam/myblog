<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use App\Actions\Resources\Concerns\SyncsRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Apply one set of changes to several records of the same type.
 *
 * Writes through each model rather than issuing a single update query, for the
 * same reason BulkDeleteRecords loops: a query-builder write fires no Eloquent
 * events, so observers and relation hooks would be skipped. One transaction
 * keeps the selection all-or-nothing.
 *
 * The caller needs the real count rather than the requested count, because the
 * success message names a number and ids can disappear between render and submit.
 */
final class BulkEditRecords
{
    use SyncsRelations;

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, int>  $ids
     * @param  array{attributes: array<string, mixed>, relations: array<string, array<int, mixed>>}  $partitioned
     */
    public function handle(string $modelClass, array $ids, array $partitioned): int
    {
        if ($ids === []) {
            return 0;
        }

        return DB::transaction(function () use ($modelClass, $ids, $partitioned): int {
            $records = $modelClass::query()->whereKey($ids)->get();

            foreach ($records as $record) {
                if ($partitioned['attributes'] !== []) {
                    $record->update($partitioned['attributes']);
                }

                $this->syncRelations($record, $partitioned['relations']);
            }

            return $records->count();
        });
    }
}

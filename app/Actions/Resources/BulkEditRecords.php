<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use App\Actions\Resources\Concerns\SyncsRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

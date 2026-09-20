<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

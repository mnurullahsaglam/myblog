<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use App\Actions\Resources\Concerns\SyncsRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Create a record and attach its many-to-many relations as one unit.
 *
 * The transaction matters: a rejected relation id would otherwise leave a
 * created row behind carrying none of the relations the form asked for.
 */
final class StoreRecord
{
    use SyncsRelations;

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array{attributes: array<string, mixed>, relations: array<string, array<int, mixed>>}  $partitioned
     */
    public function handle(string $modelClass, array $partitioned): Model
    {
        return DB::transaction(function () use ($modelClass, $partitioned): Model {
            $record = $modelClass::create($partitioned['attributes']);

            $this->syncRelations($record, $partitioned['relations']);

            return $record;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use App\Actions\Resources\Concerns\SyncsRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

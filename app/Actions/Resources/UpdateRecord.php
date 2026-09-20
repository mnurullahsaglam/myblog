<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use App\Actions\Resources\Concerns\SyncsRelations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class UpdateRecord
{
    use SyncsRelations;

    /**
     * @template TModel of Model
     *
     * @param  TModel  $record
     * @param  array{attributes: array<string, mixed>, relations: array<string, array<int, mixed>>}  $partitioned
     * @return TModel
     */
    public function handle(Model $record, array $partitioned): Model
    {
        return DB::transaction(function () use ($record, $partitioned): Model {
            $record->update($partitioned['attributes']);

            $this->syncRelations($record, $partitioned['relations']);

            return $record;
        });
    }
}

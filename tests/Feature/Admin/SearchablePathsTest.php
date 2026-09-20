<?php

declare(strict_types=1);

use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @return array<string, array{class-string<ResourceTable>}>
 */
function tableDefinitions(): array
{
    $cases = [];

    foreach (glob(__DIR__.'/../../../app/Tables/Definitions/*.php') ?: [] as $file) {
        $class = 'App\\Tables\\Definitions\\'.basename($file, '.php');

        $cases[basename($file, '.php')] = [$class];
    }

    return $cases;
}

it('searches only real columns and real relations', function (string $class): void {
    $reflection = new ReflectionClass($class);

    if ($reflection->getConstructor()?->getNumberOfRequiredParameters() > 0) {
        expect(true)->toBeTrue();

        return;
    }

    $table = $reflection->newInstance();

    $searchable = (fn (): array => $this->searchable())->call($table);

    expect($searchable)->toBeArray();
    $modelClass = (fn (): string => $this->model)->call($table);
    $model = new $modelClass;

    foreach ($searchable as $path) {
        if (! str_contains($path, '.')) {
            expect(Schema::hasColumn($model->getTable(), $path))
                ->toBeTrue("{$class} searches {$path}, which is not a column on {$model->getTable()}");

            continue;
        }

        $relation = Str::beforeLast($path, '.');
        $column = Str::afterLast($path, '.');

        expect(method_exists($model, $relation))
            ->toBeTrue("{$class} searches through {$relation}, which is not a relation");

        $related = $model->{$relation}()->getRelated();

        expect($related)->toBeInstanceOf(Model::class)
            ->and(Schema::hasColumn($related->getTable(), $column))
            ->toBeTrue("{$class} searches {$path}, which is not a column on {$related->getTable()}");
    }
})->with(fn (): array => tableDefinitions());

<?php

declare(strict_types=1);

namespace App\Tables;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A resource table: columns and filters declared once in PHP, rendered by a
 * single Vue component.
 *
 * Subclasses declare what the table contains; this base turns a request into a
 * page of rendered rows and describes the shape to the browser.
 */
abstract class ResourceTable
{
    private const MAX_PER_PAGE = 100;

    /** @var class-string<Model> */
    protected string $model;

    /** @var array<int, string> */
    protected array $with = [];

    /** @var array<int, string> */
    protected array $withCount = [];

    protected string $defaultSort = '-id';

    protected int $perPage = 25;

    /**
     * @return array<int, Column>
     */
    abstract protected function columns(): array;

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [];
    }

    /**
     * Column paths the search box and global search look through. A dotted path
     * searches the relation.
     *
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return [];
    }

    /**
     * The attribute used as the human label in global search results.
     */
    protected function titleColumn(): string
    {
        return 'name';
    }

    /**
     * @return Builder<covariant Model>
     */
    protected function query(): Builder
    {
        return $this->model::query();
    }

    /**
     * Summary tiles shown above the table, computed over the filtered set.
     *
     * @return array<int, array{label: string, value: string, caption?: string|null, icon?: string|null}>
     */
    public function tiles(Request $request): array
    {
        return [];
    }

    /**
     * The query with search and filters applied but no sort or pagination, for
     * summarising exactly what the table is showing.
     *
     * @return Builder<covariant Model>
     */
    protected function filteredQuery(Request $request): Builder
    {
        $query = $this->query();

        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);

        return $query;
    }

    /**
     * @return array{columns: array<int, array<string, mixed>>, filters: array<int, array<string, mixed>>, defaultSort: string, searchable: bool, perPage: int}
     */
    public function schema(): array
    {
        return [
            'columns' => array_map(fn (Column $column): array => $column->schema(), $this->columns()),
            'filters' => array_map(fn (Filter $filter): array => $filter->schema(), $this->filters()),
            'defaultSort' => $this->defaultSort,
            'searchable' => $this->searchable() !== [],
            'perPage' => $this->perPage,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array{id: mixed, cells: array<string, array<string, mixed>>}>
     */
    public function rows(Request $request): LengthAwarePaginator
    {
        $query = $this->query();

        if ($this->with !== []) {
            $query->with($this->with);
        }

        if ($this->withCount !== []) {
            $query->withCount($this->withCount);
        }

        $this->applySearch($query, $request);
        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $columns = $this->columns();

        $page = $request->integer('page');

        return $query
            // Page and query string come from the injected request, not the
            // global resolver, so the table is testable in isolation.
            ->paginate(
                perPage: $this->resolvePerPage($request),
                page: $page > 0 ? $page : 1,
            )
            ->appends(Arr::except($request->query(), 'page'))
            ->through(fn (Model $record): array => [
                'id' => $record->getKey(),
                'cells' => $this->resolveCells($columns, $record),
            ]);
    }

    /**
     * Lightweight rows for the command palette.
     *
     * @return Collection<int, array{id: mixed, label: string}>
     */
    public function search(string $term, int $limit): Collection
    {
        $term = trim($term);

        if ($this->searchable() === [] || $term === '') {
            /** @var Collection<int, array{id: mixed, label: string}> $empty */
            $empty = new Collection;

            return $empty;
        }

        $query = $this->query();
        $this->applySearchTerm($query, $term);

        $titleColumn = $this->titleColumn();

        /** @var array<int, array{id: mixed, label: string}> $results */
        $results = [];

        foreach ($query->limit($limit)->get() as $record) {
            $label = data_get($record, $titleColumn);
            $key = $record->getKey();

            $results[] = [
                'id' => $key,
                'label' => match (true) {
                    is_scalar($label) => (string) $label,
                    is_scalar($key) => (string) $key,
                    default => '',
                },
            ];
        }

        /** @var Collection<int, array{id: mixed, label: string}> $collection */
        $collection = new Collection($results);

        return $collection;
    }

    /**
     * @param  array<int, Column>  $columns
     * @return array<string, array<string, mixed>>
     */
    private function resolveCells(array $columns, Model $record): array
    {
        $cells = [];

        foreach ($columns as $column) {
            $cells[$column->key] = $column->resolve($record);
        }

        return $cells;
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function applySearch(Builder $query, Request $request): void
    {
        $term = $request->string('search')->trim()->toString();

        if ($term !== '') {
            $this->applySearchTerm($query, $term);
        }
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function applySearchTerm(Builder $query, string $term): void
    {
        $paths = $this->searchable();

        $query->where(function (Builder $builder) use ($paths, $term): void {
            foreach ($paths as $path) {
                if (! str_contains($path, '.')) {
                    $builder->orWhere($path, 'like', '%'.$term.'%');

                    continue;
                }

                $builder->orWhereHas(
                    Str::beforeLast($path, '.'),
                    fn (Builder $related): Builder => $related->where(Str::afterLast($path, '.'), 'like', '%'.$term.'%'),
                );
            }
        });
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        /** @var array<string, mixed> $values */
        $values = $request->array('filter');

        foreach ($this->filters() as $filter) {
            $filter->apply($query, $values[$filter->key] ?? $filter->schema()['default']);
        }
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function applySort(Builder $query, Request $request): void
    {
        $requested = $request->string('sort')->toString();
        $sort = $this->isSortable($requested) ? $requested : $this->defaultSort;

        if (! $this->isSortable($sort)) {
            $query->orderBy($query->getModel()->getKeyName(), 'desc');

            return;
        }

        $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc');
    }

    private function isSortable(string $sort): bool
    {
        if ($sort === '') {
            return false;
        }

        $column = ltrim($sort, '-');

        if ($column === 'id') {
            return true;
        }

        foreach ($this->columns() as $candidate) {
            if ($candidate->key === $column && $candidate->isSortable()) {
                return true;
            }
        }

        return false;
    }

    private function resolvePerPage(Request $request): int
    {
        $requested = $request->integer('perPage');

        return $requested <= 0 ? $this->perPage : min($requested, self::MAX_PER_PAGE);
    }
}

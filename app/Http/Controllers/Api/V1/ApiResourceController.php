<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Forms\ResourceForm;
use App\Http\Controllers\Controller;
use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The API half of AdminResourceController.
 *
 * Deliberately reuses the panel's table, form and FormRequest rather than
 * reimplementing any of them: the filters, the validation and the per-record
 * write guard are the same objects, so the two clients cannot drift apart.
 */
abstract class ApiResourceController extends Controller
{
    abstract protected function table(): ResourceTable;

    abstract protected function form(): ResourceForm;

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /**
     * The plural, hyphenated name the route is registered under.
     */
    abstract protected function resourceName(): string;

    /**
     * @return class-string<FormRequest>
     */
    abstract protected function requestClass(): string;

    /**
     * @return class-string<JsonResource>
     */
    abstract protected function resourceClass(): string;

    public function index(Request $request): AnonymousResourceCollection
    {
        $resource = $this->resourceClass();

        return $resource::collection($this->table()->records($request));
    }

    public function show(Request $request): JsonResource
    {
        $resource = $this->resourceClass();

        return new $resource($this->findOrFail($request));
    }

    public function store(): JsonResponse
    {
        ['attributes' => $attributes, 'relations' => $relations] = $this->form()->partition($this->validated());

        $model = $this->modelClass();
        $record = $model::query()->create($attributes);

        $this->syncRelations($record, $relations);

        $resource = $this->resourceClass();

        return (new $resource($record->fresh()))->response()->setStatusCode(201);
    }

    public function update(Request $request): JsonResource
    {
        $record = $this->findOrFail($request);

        abort_unless($this->isRecordEditable($record), 404);

        ['attributes' => $attributes, 'relations' => $relations] = $this->form()->partition($this->validated());

        $record->update($attributes);

        $this->syncRelations($record, $relations);

        $resource = $this->resourceClass();

        return new $resource($record->fresh());
    }

    public function destroy(Request $request): Response
    {
        $record = $this->findOrFail($request);

        abort_unless($this->isRecordEditable($record), 404);

        $record->delete();

        return response()->noContent();
    }

    /**
     * Whether this record may be written by the current request.
     */
    protected function isRecordEditable(Model $record): bool
    {
        return true;
    }

    protected function findOrFail(Request $request): Model
    {
        $model = $this->modelClass();
        $instance = new $model;

        $value = $request->route($this->routeParameter());

        if ($value instanceof Model) {
            return $value;
        }

        return $model::query()->where($instance->getRouteKeyName(), $value)->firstOrFail();
    }

    /**
     * Laravel names an apiResource parameter after the singular resource with
     * hyphens replaced, so utility-bills binds {utility_bill}.
     */
    protected function routeParameter(): string
    {
        return str_replace('-', '_', Str::singular($this->resourceName()));
    }

    /**
     * Resolving a FormRequest from the container runs its validation, which is
     * how the panel reuses the same rules. The API does the same rather than
     * revalidating, so a rule added for one protects the other.
     *
     * @return array<string, mixed>
     */
    private function validated(): array
    {
        /** @var FormRequest $request */
        $request = resolve($this->requestClass());

        /** @var array<string, mixed> $data */
        $data = $request->validated();

        return $data;
    }

    /**
     * @param  array<string, array<int, mixed>>  $relations
     */
    private function syncRelations(Model $record, array $relations): void
    {
        foreach ($relations as $relation => $ids) {
            if (! method_exists($record, $relation)) {
                continue;
            }

            $query = $record->{$relation}();

            if ($query instanceof BelongsToMany) {
                $query->sync($ids);
            }
        }
    }
}

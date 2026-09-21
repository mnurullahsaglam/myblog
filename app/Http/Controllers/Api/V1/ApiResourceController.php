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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

abstract class ApiResourceController extends Controller
{
    abstract protected function table(): ResourceTable;

    abstract protected function form(): ResourceForm;

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /**
     * @return array<string, string>
     */
    protected function uploads(): array
    {
        return [];
    }

    abstract protected function resourceName(): string;

    /**
     * @return class-string<FormRequest>
     */
    abstract protected function requestClass(): string;

    /**
     * @return class-string<JsonResource>
     */
    abstract protected function resourceClass(): string;

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'table' => $this->table()->schema(),
            'form' => $this->form()->schema(),
        ];
    }

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

        return new $resource($record->fresh())->response()->setStatusCode(201);
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

    protected function routeParameter(): string
    {
        return str_replace('-', '_', Str::singular($this->resourceName()));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(): array
    {
        /** @var FormRequest $request */
        $request = resolve($this->requestClass());

        /** @var array<string, mixed> $data */
        $data = $request->validated();

        foreach ($this->uploads() as $field => $directory) {
            if (! $request->hasFile($field)) {
                unset($data[$field]);

                continue;
            }

            $file = $request->file($field);

            $data[$field] = $file instanceof UploadedFile
                ? $file->store($directory, 'public')
                : null;
        }

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

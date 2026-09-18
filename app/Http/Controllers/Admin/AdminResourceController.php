<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Forms\ResourceForm;
use App\Http\Controllers\Controller;
use App\Support\AdminNotifier;
use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Shared CRUD for a resource declared through a ResourceTable and ResourceForm.
 *
 * Subclasses name their definitions; everything else is handled here, so adding
 * a resource is a table, a form, a FormRequest and a five-line controller.
 */
abstract class AdminResourceController extends Controller
{
    public function __construct(protected readonly AdminNotifier $notifier) {}

    abstract protected function table(): ResourceTable;

    abstract protected function form(): ResourceForm;

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    /** The route segment and route-name prefix, e.g. "posts". */
    abstract protected function resourceName(): string;

    /** The Vue page directory, e.g. "Blog/Posts". */
    abstract protected function pagePath(): string;

    /**
     * @return class-string<FormRequest>
     */
    abstract protected function requestClass(): string;

    /**
     * Upload fields mapped to their storage directory.
     *
     * @return array<string, string>
     */
    protected function uploads(): array
    {
        return [];
    }

    protected function label(): string
    {
        return Str::lower(Str::headline(Str::singular($this->resourceName())));
    }

    protected function indexRoute(): string
    {
        return 'admin.'.$this->resourceName().'.index';
    }

    /** The route parameter holding the record, e.g. "post". */
    protected function recordParameter(): string
    {
        return Str::singular($this->resourceName());
    }

    /**
     * Implicit route-model binding cannot resolve an abstract Model type hint,
     * so the record is resolved here by its own route key.
     */
    protected function resolveRecord(Request $request): Model
    {
        $value = $request->route($this->recordParameter());

        if ($value instanceof Model) {
            return $value;
        }

        $modelClass = $this->modelClass();
        $key = (new $modelClass)->getRouteKeyName();

        $record = $modelClass::query()->where($key, $value)->firstOrFail();

        // Put the model back on the route so FormRequests resolved later see a
        // record rather than a raw key - unique rules need it to ignore itself.
        $request->route()?->setParameter($this->recordParameter(), $record);

        return $record;
    }

    public function index(Request $request): Response
    {
        $table = $this->table();

        return Inertia::render($this->pagePath().'/Index', [
            'schema' => $table->schema(),
            // Closures, so a partial reload asking only for rows recomputes
            // nothing else.
            'rows' => fn (): mixed => $table->rows($request),
            'tiles' => fn (): array => $table->tiles($request),
        ]);
    }

    public function create(): Response
    {
        $form = $this->form();

        return Inertia::render($this->pagePath().'/Create', [
            'schema' => $form->schema(),
            'values' => $form->values(null),
        ]);
    }

    public function store(): RedirectResponse
    {
        $partitioned = $this->form()->partition($this->validated());

        $record = $this->modelClass()::create($partitioned['attributes']);
        $this->syncRelations($record, $partitioned['relations']);

        $this->notifier->success(Str::ucfirst($this->label()).' created');

        return to_route($this->indexRoute());
    }

    public function show(Request $request): Response
    {
        $record = $this->resolveRecord($request);
        $form = $this->form();

        return Inertia::render($this->pagePath().'/Show', [
            'schema' => $form->schema(),
            'values' => $form->values($record),
            'placeholders' => $form->placeholders($record),
            'recordId' => $record->getKey(),
        ]);
    }

    public function edit(Request $request): Response
    {
        $record = $this->resolveRecord($request);
        $form = $this->form();

        return Inertia::render($this->pagePath().'/Edit', [
            'schema' => $form->schema(),
            'values' => $form->values($record),
            'placeholders' => $form->placeholders($record),
            'recordId' => $record->getKey(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $record = $this->resolveRecord($request);
        $partitioned = $this->form()->partition($this->validated());

        $record->update($partitioned['attributes']);
        $this->syncRelations($record, $partitioned['relations']);

        $this->notifier->success(Str::ucfirst($this->label()).' updated');

        return to_route($this->indexRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->resolveRecord($request)->delete();

        $this->notifier->success(Str::ucfirst($this->label()).' deleted');

        return to_route($this->indexRoute());
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        $model = $this->modelClass();
        $table = (new $model)->getTable();

        /** @var array{ids: array<int, int>} $validated */
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:'.$table.',id'],
        ]);

        $ids = $validated['ids'];

        $model::whereIn('id', $ids)->delete();

        $this->notifier->success(count($ids).' '.Str::plural($this->label(), count($ids)).' deleted');

        return to_route($this->indexRoute());
    }

    /**
     * Resolving a FormRequest from the container runs its validation.
     *
     * @return array<string, mixed>
     */
    protected function validated(): array
    {
        /** @var FormRequest $request */
        $request = app($this->requestClass());

        /** @var array<string, mixed> $data */
        $data = $request->validated();

        foreach ($this->uploads() as $field => $directory) {
            if (! $request->hasFile($field)) {
                // No new upload: leave whatever path is already stored alone.
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
    protected function syncRelations(Model $record, array $relations): void
    {
        foreach ($relations as $key => $ids) {
            if (! method_exists($record, $key)) {
                continue;
            }

            $relation = $record->{$key}();

            if ($relation instanceof BelongsToMany) {
                $relation->sync($ids);
            }
        }
    }
}

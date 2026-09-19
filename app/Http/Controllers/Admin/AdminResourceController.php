<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Resources\BulkDeleteRecords;
use App\Actions\Resources\DeleteRecord;
use App\Actions\Resources\StoreRecord;
use App\Actions\Resources\UpdateRecord;
use App\Forms\ResourceForm;
use App\Http\Controllers\Controller;
use App\Support\AdminNotifier;
use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Model;
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
    public function __construct(
        protected readonly AdminNotifier $notifier,
        protected readonly StoreRecord $storeRecord,
        protected readonly UpdateRecord $updateRecord,
        protected readonly DeleteRecord $deleteRecord,
        protected readonly BulkDeleteRecords $bulkDeleteRecords,
    ) {}

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
     * Tiles contributed by the controller, merged ahead of the table's own.
     *
     * @return array<int, array{label: string, value: string, caption: string|null, icon: string|null}>
     */
    protected function indexTiles(): array
    {
        return [];
    }

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

        $request->route()?->setParameter($this->recordParameter(), $record);

        return $record;
    }

    public function index(Request $request): Response
    {
        $table = $this->table();

        return Inertia::render($this->pagePath().'/Index', [
            'schema' => $table->schema(),
            'rows' => fn (): mixed => $table->rows($request),
            'tiles' => fn (): array => [...$this->indexTiles(), ...$table->tiles($request)],
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
        $this->storeRecord->handle($this->modelClass(), $this->form()->partition($this->validated()));

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
        $this->updateRecord->handle(
            $this->resolveRecord($request),
            $this->form()->partition($this->validated()),
        );

        $this->notifier->success(Str::ucfirst($this->label()).' updated');

        return to_route($this->indexRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->deleteRecord->handle($this->resolveRecord($request));

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

        $deleted = $this->bulkDeleteRecords->handle($model, $validated['ids']);

        $this->notifier->success($deleted.' '.Str::plural($this->label(), $deleted).' deleted');

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
}

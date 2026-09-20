<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Resources\BulkDeleteRecords;
use App\Actions\Resources\BulkEditRecords;
use App\Actions\Resources\DeleteRecord;
use App\Actions\Resources\StoreRecord;
use App\Actions\Resources\UpdateRecord;
use App\Contracts\NotifiesAdmin;
use App\Forms\ResourceForm;
use App\Http\Controllers\Controller;
use App\Tables\ResourceTable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

abstract class AdminResourceController extends Controller
{
    public function __construct(
        protected readonly NotifiesAdmin $notifier,
        protected readonly StoreRecord $storeRecord,
        protected readonly UpdateRecord $updateRecord,
        protected readonly DeleteRecord $deleteRecord,
        protected readonly BulkDeleteRecords $bulkDeleteRecords,
        protected readonly BulkEditRecords $bulkEditRecords,
    ) {}

    abstract protected function table(): ResourceTable;

    abstract protected function form(): ResourceForm;

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    abstract protected function resourceName(): string;

    abstract protected function pagePath(): string;

    /**
     * @return class-string<FormRequest>
     */
    abstract protected function requestClass(): string;

    /**
     * @return array<int, array{label: string, value: string, caption: string|null, icon: string|null}>
     */
    protected function indexTiles(): array
    {
        return [];
    }

    /**
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

    protected function recordParameter(): string
    {
        return Str::singular(str_replace('-', '_', $this->resourceName()));
    }

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
            'schema' => [...$table->schema(), 'bulkFields' => $this->bulkFieldSchemas()],
            'rows' => fn (): mixed => $table->rows($request),
            'tiles' => fn (): array => [...$this->indexTiles(), ...$table->tiles($request)],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function bulkFieldSchemas(): array
    {
        $form = $this->form();
        $editable = $form->bulkEditableFields();

        $fields = [];

        foreach ($form->schema()['fields'] as $field) {
            if (in_array($field['key'], $editable, true)) {
                $fields[] = $field;
            }
        }

        return $fields;
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

        abort_unless($this->isRecordEditable($record), 404);

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

        abort_unless($this->isRecordEditable($record), 404);

        $this->updateRecord->handle(
            $record,
            $this->form()->partition($this->validated()),
        );

        $this->notifier->success(Str::ucfirst($this->label()).' updated');

        return to_route($this->indexRoute());
    }

    public function destroy(Request $request): RedirectResponse
    {
        $record = $this->resolveRecord($request);

        abort_unless($this->isRecordEditable($record), 404);

        $this->deleteRecord->handle($record);

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

        $deleted = $this->bulkDeleteRecords->handle($model, $this->editableIds($model, $validated['ids']));

        $this->notifier->success($deleted.' '.Str::plural($this->label(), $deleted).' deleted');

        return to_route($this->indexRoute());
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $model = $this->modelClass();
        $table = (new $model)->getTable();
        $form = $this->form();
        $editable = $form->bulkEditableFields();

        abort_if($editable === [], 404);

        /** @var array{ids: array<int, int>, field: string} $validated */
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:'.$table.',id'],
            'field' => ['required', 'string', Rule::in($editable)],
        ]);

        $field = $validated['field'];

        /** @var array{value: mixed} $value */
        $value = $request->validate($form->bulkValueRules($field));

        $updated = $this->bulkEditRecords->handle(
            $model,
            $this->editableIds($model, $validated['ids']),
            $form->partition([$field => $value['value']]),
        );

        $this->notifier->success($updated.' '.Str::plural($this->label(), $updated).' updated');

        return to_route($this->indexRoute());
    }

    protected function isRecordEditable(Model $record): bool
    {
        return true;
    }

    /**
     * @param  class-string<Model>  $model
     * @param  array<int, int>  $ids
     * @return array<int, int>
     */
    private function editableIds(string $model, array $ids): array
    {
        return array_values(array_filter(
            $ids,
            function (int $id) use ($model): bool {
                $record = $model::query()->find($id);

                return $record instanceof Model && $this->isRecordEditable($record);
            },
        ));
    }

    /**
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

<?php

declare(strict_types=1);

namespace App\Forms;

use App\Support\Access\AccessProfile;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * A resource form: fields declared once in PHP, rendered by a single Vue
 * component. Validation lives in a FormRequest, not here.
 */
abstract class ResourceForm
{
    protected int $columns = 1;

    /**
     * @return array<int, Field>
     */
    abstract protected function fields(): array;

    /**
     * The fields this request may see.
     *
     * Every other method reads this rather than fields(), so a hidden field is
     * absent from the schema, from the values, from the show page, from the bulk
     * editable whitelist and from the relations that get synced.
     *
     * @return array<int, Field>
     */
    final protected function visibleFields(): array
    {
        $profile = resolve(AccessProfile::class);

        return array_values(array_filter(
            $this->fields(),
            fn (Field $field): bool => $field->visibleTo($profile),
        ));
    }

    /**
     * @return array{fields: array<int, array<string, mixed>>, columns: int}
     */
    public function schema(): array
    {
        return [
            'fields' => array_map(fn (Field $field): array => $field->schema(), $this->visibleFields()),
            'columns' => $this->columns,
        ];
    }

    /**
     * The initial form state: a record's values, or the field defaults.
     *
     * @return array<string, mixed>
     */
    public function values(?Model $record): array
    {
        $values = [];

        foreach ($this->visibleFields() as $field) {
            if ($field->type === 'placeholder') {
                continue;
            }

            if (! $record instanceof Model) {
                $values[$field->key] = $field->defaultValue();

                continue;
            }

            $values[$field->key] = $field->isRelationSync()
                ? $this->relatedIds($record, $field->key)
                : $this->attributeValue($record, $field->key);
        }

        return $values;
    }

    /**
     * Read-only display values, keyed by field.
     *
     * @return array<string, string>
     */
    public function placeholders(?Model $record): array
    {
        if (! $record instanceof Model) {
            return [];
        }

        $values = [];

        foreach ($this->visibleFields() as $field) {
            if ($field->type !== 'placeholder') {
                continue;
            }

            $value = data_get($record, $field->key);

            $values[$field->key] = match (true) {
                $value instanceof Carbon => $value->diffForHumans(),
                is_scalar($value) => (string) $value,
                default => '-',
            };
        }

        return $values;
    }

    /**
     * Fields that can safely take one value across a whole selection.
     *
     * Restricted to choices, switches, dates and numbers: a title, a slug or an
     * uploaded file is per-record, and setting one across many rows is never
     * what was meant. Disabled fields are excluded too - the form marks those
     * computed on save, so writing them directly would be overwritten anyway.
     * This list is also the authorisation boundary for bulk editing, because
     * models are unguarded - anything outside it cannot be written in bulk.
     *
     * @return array<int, string>
     */
    public function bulkEditableFields(): array
    {
        $keys = [];

        $types = ['select', 'multiselect', 'toggle', 'date', 'datetime', 'number', 'money'];

        foreach ($this->visibleFields() as $field) {
            if (! in_array($field->type, $types, true)) {
                continue;
            }

            if ($field->schema()['disabled']) {
                continue;
            }

            $keys[] = $field->key;
        }

        return $keys;
    }

    /**
     * Validation for a bulk value, derived from the field the UI actually offered.
     *
     * Choices are checked against their own option list rather than against the
     * resource's FormRequest, which would demand every other field too.
     *
     * @return array<string, array<int, mixed>>
     */
    public function bulkValueRules(string $key): array
    {
        foreach ($this->visibleFields() as $field) {
            if ($field->key !== $key) {
                continue;
            }

            $schema = $field->schema();
            $values = array_column($schema['options'], 'value');

            return match ($field->type) {
                'multiselect' => [
                    'value' => ['present', 'array'],
                    'value.*' => ['required', Rule::in($values)],
                ],
                'toggle' => ['value' => ['required', 'boolean']],
                'number', 'money' => ['value' => $this->numericRules($schema)],
                'date', 'datetime' => ['value' => [$schema['required'] ? 'required' : 'nullable', 'date']],
                default => ['value' => [$schema['required'] ? 'required' : 'nullable', Rule::in($values)]],
            };
        }

        return ['value' => ['prohibited']];
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<int, string>
     */
    private function numericRules(array $schema): array
    {
        $rules = [$schema['required'] === true ? 'required' : 'nullable', 'numeric'];

        $meta = is_array($schema['meta']) ? $schema['meta'] : [];

        foreach (['min', 'max'] as $bound) {
            $value = $meta[$bound] ?? null;

            if (is_int($value) || is_float($value)) {
                $rules[] = $bound.':'.$value;
            }
        }

        return $rules;
    }

    /**
     * Field keys that are many-to-many relations, to be synced after save
     * rather than mass assigned.
     *
     * @return array<int, string>
     */
    public function relationKeys(): array
    {
        $keys = [];

        foreach ($this->visibleFields() as $field) {
            if ($field->isRelationSync()) {
                $keys[] = $field->key;
            }
        }

        return $keys;
    }

    /**
     * Split validated input into attributes and relations to sync.
     *
     * @param  array<string, mixed>  $data
     * @return array{attributes: array<string, mixed>, relations: array<string, array<int, mixed>>}
     */
    public function partition(array $data): array
    {
        $relations = [];

        foreach ($this->relationKeys() as $key) {
            if (array_key_exists($key, $data)) {
                /** @var array<int, mixed> $value */
                $value = is_array($data[$key]) ? $data[$key] : [];
                $relations[$key] = $value;
                unset($data[$key]);
            }
        }

        return ['attributes' => $data, 'relations' => $relations];
    }

    /**
     * @return array<int, mixed>
     */
    private function relatedIds(Model $record, string $key): array
    {
        if (! method_exists($record, $key)) {
            return [];
        }

        $relation = $record->{$key}();

        if (! $relation instanceof BelongsToMany) {
            return [];
        }

        /** @var array<int, mixed> $ids */
        $ids = $relation->pluck($relation->getRelated()->getQualifiedKeyName())->values()->all();

        return $ids;
    }

    private function attributeValue(Model $record, string $key): mixed
    {
        $value = data_get($record, $key);

        return $value instanceof BackedEnum ? $value->value : $value;
    }
}

<?php

declare(strict_types=1);

namespace App\Forms;

use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

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
     * @return array{fields: array<int, array<string, mixed>>, columns: int}
     */
    public function schema(): array
    {
        return [
            'fields' => array_map(fn (Field $field): array => $field->schema(), $this->fields()),
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

        foreach ($this->fields() as $field) {
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

        foreach ($this->fields() as $field) {
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
     * Field keys that are many-to-many relations, to be synced after save
     * rather than mass assigned.
     *
     * @return array<int, string>
     */
    public function relationKeys(): array
    {
        $keys = [];

        foreach ($this->fields() as $field) {
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

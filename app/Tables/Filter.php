<?php

declare(strict_types=1);

namespace App\Tables;

use App\Support\Contracts\HasLabel;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One filter on a resource table.
 *
 * A filter describes its control to the browser and constrains a query. A
 * display-only filter does neither of the latter: it changes how rows render
 * without touching the query, which is how the Debts table picks the currency
 * its converted-amount column reports in.
 */
final class Filter
{
    private ?string $label = null;

    private bool $multiple = false;

    private string $trueLabel = 'Yes';

    private string $falseLabel = 'No';

    private mixed $default = null;

    private bool $displayOnly = false;

    /**
     * @param  array<int, array{value: mixed, label: string}>  $options
     */
    private function __construct(
        public readonly string $key,
        public readonly string $type,
        private readonly array $options = [],
        private readonly ?string $relation = null,
        private readonly ?string $labelColumn = null,
        private readonly ?string $enumClass = null,
        private readonly ?Closure $query = null,
    ) {}

    public static function relationship(string $key, string $relation, string $labelColumn): self
    {
        return new self($key, 'select', relation: $relation, labelColumn: $labelColumn);
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     */
    public static function enum(string $key, string $enumClass): self
    {
        return new self($key, 'select', enumClass: $enumClass);
    }

    /**
     * Numeric-looking keys are coerced to ints by PHP, so accept either.
     *
     * @param  array<array-key, string>  $options
     */
    public static function select(string $key, array $options): self
    {
        return new self($key, 'select', options: self::mapOptions($options));
    }

    public static function dateRange(string $column): self
    {
        return new self($column, 'dateRange');
    }

    /** Matches on whether the column is set. */
    public static function boolean(string $column): self
    {
        return new self($column, 'boolean');
    }

    /** An arbitrary constraint, shown as a yes/no control. */
    public static function custom(string $key, string $label, Closure $query): self
    {
        return new self($key, 'boolean', query: $query)->label($label);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function multiple(bool $multiple = true): self
    {
        $this->multiple = $multiple;

        return $this;
    }

    public function trueLabel(string $label): self
    {
        $this->trueLabel = $label;

        return $this;
    }

    public function falseLabel(string $label): self
    {
        $this->falseLabel = $label;

        return $this;
    }

    public function default(mixed $default): self
    {
        $this->default = $default;

        return $this;
    }

    /**
     * Changes how rows render without constraining the query.
     */
    public function displayOnly(bool $displayOnly = true): self
    {
        $this->displayOnly = $displayOnly;

        return $this;
    }

    /**
     * @return array{key: string, type: string, label: string, multiple: bool, options: array<int, array{value: mixed, label: string}>, default: mixed, displayOnly: bool}
     */
    public function schema(): array
    {
        return [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label ?? $this->humanisedLabel(),
            'multiple' => $this->multiple,
            'options' => $this->resolveOptions(),
            'default' => $this->default,
            'displayOnly' => $this->displayOnly,
        ];
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    public function apply(Builder $query, mixed $value): void
    {
        if ($this->displayOnly || $this->isEmpty($value)) {
            return;
        }

        if ($this->query instanceof Closure) {
            ($this->query)($query);

            return;
        }

        match ($this->type) {
            'dateRange' => $this->applyDateRange($query, $value),
            'boolean' => $this->applyBoolean($query, $value),
            default => $this->applySelect($query, $value),
        };
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function applySelect(Builder $query, mixed $value): void
    {
        if (is_array($value)) {
            $query->whereIn($this->key, $value);

            return;
        }

        $query->where($this->key, $value);
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function applyDateRange(Builder $query, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $from = is_string($value['from'] ?? null) && $value['from'] !== '' ? $value['from'] : null;
        $to = is_string($value['to'] ?? null) && $value['to'] !== '' ? $value['to'] : null;

        $query
            ->when($from, fn (Builder $builder, string $date): Builder => $builder->whereDate($this->key, '>=', $date))
            ->when($to, fn (Builder $builder, string $date): Builder => $builder->whereDate($this->key, '<=', $date));
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function applyBoolean(Builder $query, mixed $value): void
    {
        if (in_array($value, ['yes', true, '1'], true)) {
            $query->whereNotNull($this->key);

            return;
        }

        if (in_array($value, ['no', false, '0'], true)) {
            $query->whereNull($this->key);
        }
    }

    private function isEmpty(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            return collect($value)->filter(fn (mixed $item): bool => $item !== null && $item !== '')->isEmpty();
        }

        return false;
    }

    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    private function resolveOptions(): array
    {
        return match (true) {
            $this->type === 'boolean' => [
                ['value' => 'yes', 'label' => $this->trueLabel],
                ['value' => 'no', 'label' => $this->falseLabel],
            ],
            $this->type === 'dateRange' => [],
            $this->enumClass !== null => $this->enumOptions(),
            $this->relation !== null && $this->labelColumn !== null => $this->relationshipOptions(),
            default => $this->options,
        };
    }

    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    private function enumOptions(): array
    {
        $options = [];

        /** @var class-string<BackedEnum> $enumClass */
        $enumClass = $this->enumClass;

        foreach ($enumClass::cases() as $case) {
            $options[] = [
                'value' => $case->value,
                'label' => $case instanceof HasLabel ? $case->getLabel() : (string) $case->value,
            ];
        }

        return $options;
    }

    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    private function relationshipOptions(): array
    {
        $related = $this->relatedModelClass();

        if ($related === null || $this->labelColumn === null) {
            return [];
        }

        $labelColumn = $this->labelColumn;

        return $related::query()
            ->orderBy($labelColumn)
            ->get(['id', $labelColumn])
            ->map(function (Model $record) use ($labelColumn): array {
                $label = $record->getAttribute($labelColumn);

                return [
                    'value' => $record->getKey(),
                    'label' => is_scalar($label) ? (string) $label : '',
                ];
            })
            ->all();
    }

    /**
     * Derived from the foreign key: expense_category_id becomes ExpenseCategory.
     *
     * @return class-string<Model>|null
     */
    private function relatedModelClass(): ?string
    {
        $class = 'App\\Models\\'.Str::studly(Str::beforeLast($this->key, '_id'));

        return class_exists($class) && is_subclass_of($class, Model::class) ? $class : null;
    }

    /**
     * @param  array<array-key, string>  $options
     * @return array<int, array{value: mixed, label: string}>
     */
    private static function mapOptions(array $options): array
    {
        $mapped = [];

        foreach ($options as $value => $label) {
            $mapped[] = ['value' => (string) $value, 'label' => $label];
        }

        return $mapped;
    }

    private function humanisedLabel(): string
    {
        return Str::ucfirst(
            Str::of($this->key)->beforeLast('_id')->snake(' ')->replace('_', ' ')->toString()
        );
    }
}

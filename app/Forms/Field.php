<?php

declare(strict_types=1);

namespace App\Forms;

use App\Support\Contracts\HasLabel;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One field of a resource form.
 *
 * A field describes its control to the browser. Validation is not derived from
 * it - every resource has a real FormRequest - so this stays about presentation.
 */
final class Field
{
    private ?string $label = null;

    private bool $required = false;

    private bool $disabled = false;

    private ?string $help = null;

    private ?string $placeholder = null;

    private mixed $default = null;

    private int $columnSpan = 1;

    /** @var array<string, mixed> */
    private array $meta = [];

    /** @var array<int, array{value: mixed, label: string}> */
    private array $options = [];

    /** @var array<int, self> */
    private array $fields = [];

    private function __construct(
        public readonly string $key,
        public readonly string $type,
        private readonly ?string $relation = null,
        private readonly ?string $labelColumn = null,
        private readonly ?string $enumClass = null,
        /** @var class-string<Model>|null */
        private readonly ?string $relatedModel = null,
    ) {}

    public static function text(string $key): self
    {
        return new self($key, 'text');
    }

    /**
     * An ISBN with a lookup button beside it.
     *
     * Its own type rather than a text field because ResourceForm.vue has no
     * slots, so the Fetch affordance cannot be layered on from the page. Being
     * part of the schema, it appears on Create and Edit alike.
     */
    public static function isbn(string $key): self
    {
        return new self($key, 'isbn');
    }

    /**
     * A repeating group of sub-fields, edited as rows.
     *
     * Its own type rather than a bespoke widget because ResourceForm.vue has no
     * slots. The renderer reuses FormField for each sub-field, so every existing
     * type works inside a repeater without further work.
     *
     * @param  array<int, self>  $fields
     */
    public static function repeater(string $key, array $fields): self
    {
        $field = new self($key, 'repeater');
        $field->fields = $fields;

        return $field;
    }

    public static function textarea(string $key): self
    {
        return new self($key, 'textarea');
    }

    public static function markdown(string $key): self
    {
        return new self($key, 'markdown');
    }

    /**
     * Long-form prose. Rendered as markdown rather than a WYSIWYG: the panel is
     * a developer tool and this avoids pulling in an editor dependency.
     */
    public static function richtext(string $key): self
    {
        return new self($key, 'markdown');
    }

    public static function number(string $key): self
    {
        return new self($key, 'number');
    }

    public static function money(string $key): self
    {
        return new self($key, 'money');
    }

    /**
     * Numeric-looking keys are coerced to ints by PHP, so accept either.
     *
     * @param  array<array-key, string>  $options
     */
    public static function select(string $key, array $options): self
    {
        $field = new self($key, 'select');
        $field->options = self::mapOptions($options);

        return $field;
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     */
    public static function enum(string $key, string $enumClass): self
    {
        return new self($key, 'select', enumClass: $enumClass);
    }

    /** A belongsTo, chosen by id. */
    public static function relationship(string $key, string $relation, string $labelColumn): self
    {
        return new self($key, 'select', relation: $relation, labelColumn: $labelColumn);
    }

    /**
     * A many-to-many, held as an array of ids and synced after save.
     *
     * @param  class-string<Model>  $relatedModel
     */
    public static function multiRelationship(string $key, string $relatedModel, string $labelColumn): self
    {
        return new self($key, 'multiselect', labelColumn: $labelColumn, relatedModel: $relatedModel);
    }

    public static function date(string $key): self
    {
        return new self($key, 'date');
    }

    public static function datetime(string $key): self
    {
        return new self($key, 'datetime');
    }

    public static function file(string $key): self
    {
        return new self($key, 'file');
    }

    public static function image(string $key): self
    {
        return new self($key, 'image');
    }

    public static function toggle(string $key): self
    {
        return new self($key, 'toggle');
    }

    public static function tags(string $key): self
    {
        return new self($key, 'tags');
    }

    public static function hidden(string $key): self
    {
        return new self($key, 'hidden');
    }

    /** A read-only display, such as "created 3 days ago". */
    public static function placeholder(string $key): self
    {
        return new self($key, 'placeholder');
    }

    /** A read-only value shown in a mono bar with a copy button. */
    public static function readonlyCode(string $key): self
    {
        return new self($key, 'readonlyCode');
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function disabled(bool $disabled = true): self
    {
        $this->disabled = $disabled;

        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function placeholderText(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function default(mixed $default): self
    {
        $this->default = $default;

        return $this;
    }

    public function rows(int $rows): self
    {
        $this->meta['rows'] = $rows;

        return $this;
    }

    public function step(float $step): self
    {
        $this->meta['step'] = $step;

        return $this;
    }

    public function min(float $min): self
    {
        $this->meta['min'] = $min;

        return $this;
    }

    public function max(float $max): self
    {
        $this->meta['max'] = $max;

        return $this;
    }

    public function prefix(string $prefix): self
    {
        $this->meta['prefix'] = $prefix;

        return $this;
    }

    public function searchable(bool $searchable = true): self
    {
        $this->meta['searchable'] = $searchable;

        return $this;
    }

    public function directory(string $directory): self
    {
        $this->meta['directory'] = $directory;

        return $this;
    }

    /**
     * @param  array<int, string>  $mimeTypes
     */
    public function accept(array $mimeTypes): self
    {
        $this->meta['accept'] = $mimeTypes;

        return $this;
    }

    /** Keep this field in sync with a slugified other field, client-side. */
    public function slugFrom(string $sourceKey): self
    {
        $this->meta['slugFrom'] = $sourceKey;

        return $this;
    }

    public function columnSpan(int $span): self
    {
        $this->columnSpan = $span;

        return $this;
    }

    public function defaultValue(): mixed
    {
        return $this->default;
    }

    /** Many-to-many fields are synced after save, not mass assigned. */
    public function isRelationSync(): bool
    {
        return $this->type === 'multiselect';
    }

    /**
     * @return array{key: string, type: string, label: string, required: bool, disabled: bool, help: string|null, placeholder: string|null, default: mixed, options: array<int, array{value: mixed, label: string}>, meta: array<string, mixed>, columnSpan: int, fields?: array<int, array<string, mixed>>}
     */
    public function schema(): array
    {
        $schema = [
            'key' => $this->key,
            'type' => $this->type,
            'label' => $this->label ?? $this->humanisedLabel(),
            'required' => $this->required,
            'disabled' => $this->disabled,
            'help' => $this->help,
            'placeholder' => $this->placeholder,
            'default' => $this->default,
            'options' => $this->resolveOptions(),
            'meta' => $this->meta,
            'columnSpan' => $this->columnSpan,
        ];

        if ($this->type === 'repeater') {
            $schema['fields'] = array_map(fn (self $field): array => $field->schema(), $this->fields);
        }

        return $schema;
    }

    /**
     * @return array<int, array{value: mixed, label: string}>
     */
    private function resolveOptions(): array
    {
        if ($this->enumClass !== null) {
            /** @var class-string<BackedEnum> $enumClass */
            $enumClass = $this->enumClass;

            $options = [];

            foreach ($enumClass::cases() as $case) {
                $options[] = [
                    'value' => $case->value,
                    'label' => $case instanceof HasLabel ? $case->getLabel() : (string) $case->value,
                ];
            }

            return $options;
        }

        $related = $this->relatedModel ?? ($this->relation !== null ? $this->relatedModelClass() : null);

        if ($related !== null && $this->labelColumn !== null && is_subclass_of($related, Model::class)) {
            return $this->modelOptions($related, $this->labelColumn);
        }

        return $this->options;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, array{value: mixed, label: string}>
     */
    private function modelOptions(string $modelClass, string $labelColumn): array
    {
        $options = [];

        foreach ($modelClass::query()->orderBy($labelColumn)->get(['id', $labelColumn]) as $record) {
            $label = $record->getAttribute($labelColumn);

            $options[] = [
                'value' => $record->getKey(),
                'label' => is_scalar($label) ? (string) $label : '',
            ];
        }

        return $options;
    }

    /**
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

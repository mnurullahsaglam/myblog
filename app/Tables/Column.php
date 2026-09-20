<?php

declare(strict_types=1);

namespace App\Tables;

use App\Enums\Ability;
use App\Enums\Currencies;
use App\Support\Access\AccessProfile;
use App\Support\Contracts\HasColor;
use App\Support\Contracts\HasLabel;
use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One column of a resource table.
 *
 * A column knows how to describe itself to the browser and how to turn a model
 * into a rendered cell. Presentation resolves here, on the server, because the
 * colour and state closures are PHP and cannot cross to Vue - which is what
 * keeps the Vue layer free of any model knowledge.
 */
final class Column
{
    private ?string $label = null;

    private bool $sortable = false;

    private bool $toggleable = false;

    private bool $hiddenByDefault = false;

    private ?int $limit = null;

    private bool $tooltip = false;

    private ?string $default = null;

    private string|Closure|null $color = null;

    private ?string $align = null;

    private bool $circular = false;

    private ?int $size = null;

    private ?Closure $state = null;

    private function __construct(
        public readonly string $key,
        public readonly string $type,
        private readonly ?string $currency = null,
        private readonly ?string $currencyFrom = null,
    ) {}

    private ?Ability $requires = null;

    /**
     * Hidden from anyone without this ability, everywhere rather than only in
     * the header: the definition is what every other method reads, so removing
     * it removes the value from the payload too.
     */
    public function hiddenWithout(Ability $ability): self
    {
        $this->requires = $ability;

        return $this;
    }

    public function visibleTo(AccessProfile $profile): bool
    {
        return ! $this->requires instanceof Ability || $profile->allows($this->requires);
    }

    public static function text(string $key): self
    {
        return new self($key, 'text');
    }

    /**
     * @param  string|null  $currency  a fixed currency code
     * @param  string|null  $currencyFrom  another attribute holding the code
     */
    public static function money(string $key, ?string $currency = null, ?string $currencyFrom = null): self
    {
        return new self($key, 'money', currency: $currency, currencyFrom: $currencyFrom);
    }

    public static function date(string $key): self
    {
        return new self($key, 'date');
    }

    public static function datetime(string $key): self
    {
        return new self($key, 'datetime');
    }

    public static function badge(string $key): self
    {
        return new self($key, 'badge');
    }

    public static function image(string $key): self
    {
        return new self($key, 'image');
    }

    public static function boolean(string $key): self
    {
        return new self($key, 'boolean');
    }

    /** A quantity, rendered with thousands separators. */
    public static function count(string $key): self
    {
        return new self($key, 'count');
    }

    /** A bare number such as a year or an edition, with no separators. */
    public static function number(string $key): self
    {
        return new self($key, 'number');
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function sortable(bool $sortable = true): self
    {
        $this->sortable = $sortable;

        return $this;
    }

    public function toggleable(bool $toggleable = true, bool $hiddenByDefault = false): self
    {
        $this->toggleable = $toggleable;
        $this->hiddenByDefault = $hiddenByDefault;

        return $this;
    }

    public function limit(int $characters): self
    {
        $this->limit = $characters;

        return $this;
    }

    /** Show the untruncated value on hover. Only meaningful alongside limit(). */
    public function tooltip(bool $tooltip = true): self
    {
        $this->tooltip = $tooltip;

        return $this;
    }

    public function default(string $default): self
    {
        $this->default = $default;

        return $this;
    }

    /**
     * A semantic variant, or a closure receiving the record.
     */
    public function color(string|Closure $color): self
    {
        $this->color = $color;

        return $this;
    }

    public function align(string $align): self
    {
        $this->align = $align;

        return $this;
    }

    public function circular(bool $circular = true): self
    {
        $this->circular = $circular;

        return $this;
    }

    public function size(int $pixels): self
    {
        $this->size = $pixels;

        return $this;
    }

    public function numeric(): self
    {
        $this->align = 'right';

        return $this;
    }

    /** Compute the value from the record instead of reading an attribute. */
    public function state(Closure $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function isSortable(): bool
    {
        return $this->sortable;
    }

    /**
     * @return array{key: string, label: string, type: string, sortable: bool, toggleable: bool, hiddenByDefault: bool, align: string}
     */
    public function schema(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label ?? $this->humanisedLabel(),
            'type' => $this->type,
            'sortable' => $this->sortable,
            'toggleable' => $this->toggleable,
            'hiddenByDefault' => $this->hiddenByDefault,
            'align' => $this->align ?? $this->defaultAlign(),
        ];
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    public function resolve(Model $record): array
    {
        $raw = $this->state instanceof Closure
            ? ($this->state)($record)
            : data_get($record, $this->key);

        return match ($this->type) {
            'money' => $this->resolveMoney($record, $raw),
            'date' => $this->resolveDate($raw, 'd M Y'),
            'datetime' => $this->resolveDate($raw, 'd M Y, H:i'),
            'badge' => $this->resolveBadge($record, $raw),
            'image' => $this->resolveImage($raw),
            'boolean' => $this->resolveBoolean($raw),
            'count' => $this->resolveCount($raw),
            'number' => $this->resolveNumber($raw),
            default => $this->resolveText($record, $raw),
        };
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveText(Model $record, mixed $raw): array
    {
        $full = $this->stringify($raw);

        if ($full === '') {
            return $this->payload($this->default ?? '', $raw);
        }

        $variant = $this->resolveVariant($record, $raw);

        if ($this->limit === null || mb_strlen($full) <= $this->limit) {
            return $this->payload($full, $raw, variant: $variant);
        }

        return $this->payload(
            Str::limit($full, $this->limit),
            $raw,
            variant: $variant,
            tooltip: $this->tooltip ? $full : null,
        );
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveMoney(Model $record, mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return $this->payload($this->default ?? '', null);
        }

        $amount = is_numeric($raw) ? (float) $raw : 0.0;
        $code = $this->currencyCode($record);
        $symbol = Currencies::tryFrom($code)?->getSymbol() ?? $code.' ';

        return $this->payload($symbol.number_format($amount, 2), $amount);
    }

    private function currencyCode(Model $record): string
    {
        if ($this->currency !== null) {
            return $this->currency;
        }

        if ($this->currencyFrom === null) {
            return Currencies::TRY->value;
        }

        $value = data_get($record, $this->currencyFrom);

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : Currencies::TRY->value;
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveDate(mixed $raw, string $format): array
    {
        if ($raw === null || $raw === '') {
            return $this->payload($this->default ?? '', null);
        }

        $date = $raw instanceof Carbon ? $raw : Date::parse($this->stringify($raw));

        return $this->payload(
            $date->format($format),
            $format === 'd M Y' ? $date->toDateString() : $date->toIso8601String(),
        );
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveBadge(Model $record, mixed $raw): array
    {
        $display = $raw instanceof HasLabel ? $raw->getLabel() : $this->stringify($raw);

        if ($display === '') {
            $display = $this->default ?? '';
        }

        return $this->payload(
            $display,
            $raw instanceof BackedEnum ? $raw->value : $raw,
            variant: $this->resolveVariant($record, $raw) ?? 'secondary',
        );
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveImage(mixed $raw): array
    {
        $path = $this->stringify($raw);

        return $this->payload(
            $path === '' ? '' : Storage::disk('public')->url($path),
            $path === '' ? null : $path,
            meta: ['circular' => $this->circular, 'size' => $this->size ?? 32],
        );
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveBoolean(mixed $raw): array
    {
        $value = (bool) $raw;

        return $this->payload($value ? 'Yes' : 'No', $value, variant: $value ? 'success' : 'gray');
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveCount(mixed $raw): array
    {
        if ($raw === null) {
            return $this->payload($this->default ?? '0', 0);
        }

        $value = is_numeric($raw) ? (int) $raw : 0;

        return $this->payload(number_format($value), $value);
    }

    /**
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function resolveNumber(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return $this->payload($this->default ?? '', null);
        }

        $value = is_numeric($raw) ? (int) $raw : 0;

        return $this->payload((string) $value, $value);
    }

    private function resolveVariant(Model $record, mixed $raw): ?string
    {
        if ($this->color instanceof Closure) {
            $resolved = ($this->color)($record);

            return is_string($resolved) ? $resolved : null;
        }

        if (is_string($this->color)) {
            return $this->color;
        }

        return $raw instanceof HasColor ? $raw->getColor() : null;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{display: string, raw: mixed, variant: string|null, tooltip: string|null, meta: array<string, mixed>}
     */
    private function payload(string $display, mixed $raw, ?string $variant = null, ?string $tooltip = null, array $meta = []): array
    {
        return [
            'display' => $display,
            'raw' => $raw,
            'variant' => $variant,
            'tooltip' => $tooltip,
            'meta' => $meta,
        ];
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * "created_at" becomes "Created at"; "writer.name" becomes "Writer".
     */
    private function humanisedLabel(): string
    {
        $segments = explode('.', $this->key);
        $last = end($segments);

        if ($last === 'name' && count($segments) > 1) {
            $last = $segments[count($segments) - 2];
        }

        $last = (string) Str::of($last)->beforeLast('_count');

        return Str::ucfirst(Str::of($last)->snake(' ')->replace('_', ' ')->toString());
    }

    private function defaultAlign(): string
    {
        return in_array($this->type, ['money', 'count', 'number'], true) ? 'right' : 'left';
    }
}

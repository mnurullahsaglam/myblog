<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * One date format for the whole API.
 *
 * Eloquent serialises a date-cast column and a datetime-cast column differently,
 * and a hand-written resource differs again. A client decoding JSON has one date
 * strategy, so three formats mean three special cases forever. Dates are Y-m-d
 * and datetimes are ISO 8601 with an offset, everywhere.
 */
abstract class ApiResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function normalisedAttributes(): array
    {
        /** @var Model $model */
        $model = $this->resource;

        $attributes = $model->attributesToArray();
        $casts = $model->getCasts();

        foreach ($attributes as $key => $value) {
            if ($value === null) {
                continue;
            }

            $raw = $model->getAttribute($key);

            if (! $raw instanceof DateTimeInterface) {
                continue;
            }

            $cast = $casts[$key] ?? null;
            $dateOnly = is_string($cast) && ($cast === 'date' || str_starts_with($cast, 'date:'));

            $attributes[$key] = $dateOnly
                ? Carbon::instance($raw)->toDateString()
                : Carbon::instance($raw)->toIso8601String();
        }

        return $attributes;
    }
}

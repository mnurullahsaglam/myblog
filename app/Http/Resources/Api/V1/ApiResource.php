<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;

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
                ? Date::instance($raw)->toDateString()
                : Date::instance($raw)->toIso8601String();
        }

        return $attributes;
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $key
 * @property string $endpoint
 * @property string $payload_hash
 * @property int $response_status
 * @property string $response_body
 * @property Carbon $expires_at
 */
final class IdempotencyKey extends Model
{
    /**
     * @param  Builder<IdempotencyKey>  $query
     * @return Builder<IdempotencyKey>
     */
    protected function scopeLive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }
}

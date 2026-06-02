<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $date
 * @property int $total_seconds
 * @property array<string, mixed>|null $raw
 * @property-read string $total_human
 * @property-read Collection<int, WakaTimeSummaryEntry> $entries
 */
class WakaTimeSummary extends Model
{
    protected $fillable = [
        'date',
        'total_seconds',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'total_seconds' => 'integer',
            'raw' => 'array',
        ];
    }

    /**
     * @return HasMany<WakaTimeSummaryEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(WakaTimeSummaryEntry::class);
    }

    /**
     * @return HasMany<WakaTimeSummaryEntry, $this>
     */
    public function entriesOfType(string $type): HasMany
    {
        return $this->entries()->where('type', $type);
    }

    public function getTotalHumanAttribute(): string
    {
        $hours = intdiv($this->total_seconds, 3600);
        $minutes = intdiv($this->total_seconds % 3600, 60);

        return sprintf('%dh %dm', $hours, $minutes);
    }
}

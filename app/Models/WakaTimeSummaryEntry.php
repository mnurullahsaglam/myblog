<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $waka_time_summary_id
 * @property string $type
 * @property string $name
 * @property int $seconds
 * @property string $percent
 * @property-read WakaTimeSummary $summary
 */
class WakaTimeSummaryEntry extends Model
{
    public const TYPE_PROJECT = 'project';

    public const TYPE_LANGUAGE = 'language';

    public const TYPE_EDITOR = 'editor';

    public const TYPE_OS = 'os';

    public const TYPE_CATEGORY = 'category';

    protected $fillable = [
        'waka_time_summary_id',
        'type',
        'name',
        'seconds',
        'percent',
    ];

    protected function casts(): array
    {
        return [
            'seconds' => 'integer',
            'percent' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<WakaTimeSummary, $this>
     */
    public function summary(): BelongsTo
    {
        return $this->belongsTo(WakaTimeSummary::class, 'waka_time_summary_id');
    }
}

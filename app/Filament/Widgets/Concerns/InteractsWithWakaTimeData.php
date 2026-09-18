<?php

declare(strict_types=1);

namespace App\Filament\Widgets\Concerns;

use App\Support\WakaTime\AggregatesWakaTimeData;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Adapter keeping the existing Filament widgets working while the panel is
 * rebuilt. The aggregation itself lives in AggregatesWakaTimeData; this only
 * supplies the range from Filament's page filters.
 */
trait InteractsWithWakaTimeData
{
    use AggregatesWakaTimeData, InteractsWithPageFilters;

    protected function rangeFilter(): ?string
    {
        $range = $this->pageFilters['range'] ?? null;

        return is_scalar($range) ? (string) $range : null;
    }
}

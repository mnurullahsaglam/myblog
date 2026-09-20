<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\WakaTimeSummary;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin WakaTimeSummary
 */
final class WakaTimeSummaryResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return $this->normalisedAttributes();
    }
}

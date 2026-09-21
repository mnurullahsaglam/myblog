<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Writer;
use Illuminate\Http\Request;
use Override;

/**
 * @mixin Writer
 */
final class WriterResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        return $this->normalisedAttributes();
    }

    /**
     * @return array<int, string>
     */
    #[Override]
    protected function fileAttributes(): array
    {
        return ['image'];
    }
}

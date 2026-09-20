<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Book;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Override;

/**
 * @mixin Book
 */
final class BookResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    #[Override]
    public function toArray(Request $request): array
    {
        /** @var Model $model */
        $model = $this->resource;

        return $model->attributesToArray();
    }
}

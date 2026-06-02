<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait ImageUrlAttribute
{
    /**
     * @return Attribute<string, never>
     */
    public function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => asset((string) $this->image),
        );
    }
}

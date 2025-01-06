<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait ImageUrlAttribute
{
    public function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn() => asset($this->image),
        );
    }
}

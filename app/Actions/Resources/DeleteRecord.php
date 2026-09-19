<?php

declare(strict_types=1);

namespace App\Actions\Resources;

use Illuminate\Database\Eloquent\Model;

final class DeleteRecord
{
    public function handle(Model $record): void
    {
        $record->delete();
    }
}

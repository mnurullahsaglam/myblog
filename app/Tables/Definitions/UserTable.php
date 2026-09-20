<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\User;
use App\Tables\Column;
use App\Tables\ResourceTable;
use Override;

final class UserTable extends ResourceTable
{
    #[Override]
    protected string $model = User::class;

    /**
     * @return array<int, Column>
     */
    protected function columns(): array
    {
        return [
            Column::text('name')->sortable(),
            Column::text('email')->sortable(),
            Column::badge('role')->state(fn (User $record): string => $record->role->name),
            Column::datetime('created_at')->label('Joined')->sortable(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['name', 'email'];
    }
}

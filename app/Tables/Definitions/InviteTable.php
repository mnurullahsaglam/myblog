<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Models\Invite;
use App\Tables\Column;
use App\Tables\ResourceTable;
use Override;

final class InviteTable extends ResourceTable
{
    #[Override]
    protected string $model = Invite::class;

    #[Override]
    protected array $with = ['invitedBy'];

    /**
     * @return array<int, Column>
     */
    protected function columns(): array
    {
        return [
            Column::text('email')->sortable(),
            Column::badge('role')->state(fn (Invite $record): string => $record->role->name),
            Column::badge('status')
                ->state(fn (Invite $record): string => $record->status->getLabel())
                ->color(fn (Invite $record): string => $record->status->getColor()),
            Column::datetime('expires_at')->label('Expires')->sortable(),
            Column::text('invitedBy.name')->label('Sent by')->default('—'),
            Column::datetime('created_at')->label('Sent')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return ['email'];
    }
}

<?php

declare(strict_types=1);

namespace App\Tables\Definitions;

use App\Enums\UtilityType;
use App\Models\UtilityAccount;
use App\Tables\Column;
use App\Tables\Filter;
use App\Tables\ResourceTable;
use Override;

final class UtilityAccountTable extends ResourceTable
{
    #[Override]
    protected string $model = UtilityAccount::class;

    #[Override]
    protected array $withCount = ['bills'];

    /**
     * @return array<int, Column>
     */
    protected function columns(): array
    {
        return [
            Column::badge('type')->state(fn (UtilityAccount $record): string => $record->type->getLabel()),
            Column::text('label')->sortable(),
            Column::text('provider')->sortable(),
            Column::text('subscriber_no')->label('Subscriber no')->default('—'),
            Column::count('bills_count')->label('Bills'),
            Column::boolean('is_active')->label('Active'),
            Column::datetime('created_at')->label('Created')->sortable()->toggleable(hiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, Filter>
     */
    protected function filters(): array
    {
        return [
            Filter::enum('type', UtilityType::class)->multiple(),
            Filter::boolean('is_active')->label('Active'),
        ];
    }
}

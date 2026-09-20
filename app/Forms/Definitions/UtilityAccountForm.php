<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Enums\UtilityType;
use App\Forms\Field;
use App\Forms\ResourceForm;
use Override;

final class UtilityAccountForm extends ResourceForm
{
    #[Override]
    protected int $columns = 2;

    /**
     * @return array<int, Field>
     */
    protected function fields(): array
    {
        return [
            Field::enum('type', UtilityType::class)->required(),
            Field::text('provider')->required()->help('Enerjisa, İGDAŞ, Turkcell.'),
            Field::text('label')->required()->help('What you call it: Ev elektrik, İş telefonu.'),
            Field::text('subscriber_no')->label('Subscriber no')->help('Abone or tesisat numarası.'),
            Field::toggle('is_active')->label('Active')->default(true),
            Field::placeholder('created_at')->label('Created'),
        ];
    }
}

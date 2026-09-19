<?php

declare(strict_types=1);

namespace App\Forms\Definitions;

use App\Forms\Field;
use App\Forms\ResourceForm;
use App\Models\Category;
use Override;

final class PostForm extends ResourceForm
{
    #[Override]
    protected int $columns = 1;

    protected function fields(): array
    {
        return [
            Field::text('title')->required(),
            Field::readonlyCode('slug')->slugFrom('title')->help('Derived from the title.'),
            Field::multiRelationship('categories', Category::class, 'name')->label('Categories'),
            Field::image('image')->directory('posts')->accept(['image/jpeg', 'image/png', 'image/webp']),
            Field::markdown('content')->required()->rows(22),
            Field::placeholder('created_at')->label('Created'),
            Field::placeholder('updated_at')->label('Last modified'),
        ];
    }
}

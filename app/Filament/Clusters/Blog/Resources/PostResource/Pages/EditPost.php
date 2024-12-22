<?php

namespace App\Filament\Clusters\Blog\Resources\PostResource\Pages;

use App\Filament\Clusters\Blog\Resources\PostResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPost extends EditRecord
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

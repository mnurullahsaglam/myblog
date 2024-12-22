<?php

namespace App\Filament\Clusters\Blog\Resources\PostResource\Pages;

use App\Filament\Clusters\Blog\Resources\PostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPosts extends ListRecords
{
    protected static string $resource = PostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

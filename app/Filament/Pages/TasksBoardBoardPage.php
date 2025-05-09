<?php

namespace App\Filament\Pages;

use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\Flowforge\Filament\Pages\KanbanBoardPage;

class TasksBoardBoardPage extends KanbanBoardPage
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    protected static ?string $navigationLabel = 'Tasks Board Board Page';
    protected static ?string $title = 'Task Board';

    public function getSubject(): Builder
    {
        return Task::query();
    }

    public function mount(): void
    {
        $this
            ->titleField('title')
            ->orderField('sort_order')
            ->columnField('status')
            ->columns([
                'todo' => 'To Do',
                'in_progress' => 'In Progress',
                'completed' => 'Completed',
            ])
            ->columnColors([
                'todo' => 'blue',
                'in_progress' => 'yellow',
                'completed' => 'green',
            ]);
    }
}

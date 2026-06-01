<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Task;
use App\Services\GitHubService;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\BoardPage;
use Relaticle\Flowforge\Column;

class TasksBoardBoardPage extends BoardPage
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-view-columns';

    protected static ?string $navigationLabel = 'Tasks Board';

    protected static ?string $title = 'Task Board';

    public ?Task $selectedTask = null;

    public bool $showTaskModal = false;

    public function board(Board $board): Board
    {
        return $board
            ->query(Task::query()->with(['repository', 'project']))
            ->recordTitleAttribute('title')
            ->columnIdentifier('status')
            ->positionIdentifier('sort_order')
            ->columns([
                Column::make('todo')->label('To Do')->color('blue'),
                Column::make('in_progress')->label('In Progress')->color('warning'),
                Column::make('completed')->label('Completed')->color('success'),
            ])
            ->recordActions([
                Action::make('viewTask')
                    ->label('View Details')
                    ->icon('heroicon-o-eye')
                    ->action(function (array $arguments) {
                        $this->selectedTask = Task::find($arguments['record']);
                        $this->showTaskModal = true;
                    }),

                Action::make('editTask')
                    ->label('Edit Task')
                    ->icon('heroicon-o-pencil')
                    ->form([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->rows(3),

                        Select::make('status')
                            ->options([
                                'todo' => 'To Do',
                                'in_progress' => 'In Progress',
                                'completed' => 'Completed',
                            ])
                            ->required(),

                        Select::make('repository_id')
                            ->relationship('repository', 'name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->fillForm(function (array $arguments): array {
                        $task = Task::find($arguments['record']);

                        return $task ? $task->toArray() : [];
                    })
                    ->action(function (array $arguments, array $data) {
                        $task = Task::find($arguments['record']);
                        if ($task) {
                            $task->update($data);

                            Notification::make()
                                ->title('Task updated successfully')
                                ->success()
                                ->send();
                        }
                    }),

                ActionGroup::make([
                    Action::make('syncToGitHub')
                        ->label('Sync to GitHub')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn (array $arguments) => Task::find($arguments['record'])?->is_github_issue)
                        ->requiresConfirmation()
                        ->action(function (array $arguments) {
                            $task = Task::find($arguments['record']);
                            if ($task && $task->is_github_issue) {
                                try {
                                    $githubService = app(GitHubService::class);
                                    $success = $githubService->updateIssue($task);

                                    Notification::make()
                                        ->title($success ? 'Synced to GitHub successfully' : 'Failed to sync to GitHub')
                                        ->{$success ? 'success' : 'danger'}()
                                        ->send();
                                } catch (Exception $e) {
                                    Notification::make()
                                        ->title('Error: '.$e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }
                        }),

                    Action::make('createGitHubIssue')
                        ->label('Create GitHub Issue')
                        ->icon('heroicon-o-plus')
                        ->visible(function (array $arguments) {
                            $task = Task::find($arguments['record']);

                            return $task && $task->repository && ! $task->is_github_issue;
                        })
                        ->requiresConfirmation()
                        ->action(function (array $arguments) {
                            $task = Task::find($arguments['record']);
                            if ($task && $task->repository && ! $task->is_github_issue) {
                                try {
                                    $githubService = app(GitHubService::class);
                                    $issueData = $githubService->createIssue($task);

                                    Notification::make()
                                        ->title($issueData ? 'GitHub issue created successfully' : 'Failed to create GitHub issue')
                                        ->{$issueData ? 'success' : 'danger'}()
                                        ->send();
                                } catch (Exception $e) {
                                    Notification::make()
                                        ->title('Error: '.$e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }
                        }),

                    Action::make('addComment')
                        ->label('Add GitHub Comment')
                        ->icon('heroicon-o-chat-bubble-left')
                        ->visible(fn (array $arguments) => Task::find($arguments['record'])?->is_github_issue)
                        ->form([
                            Textarea::make('comment')
                                ->label('Comment')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (array $arguments, array $data) {
                            $task = Task::find($arguments['record']);
                            if ($task && $task->is_github_issue) {
                                try {
                                    $githubService = app(GitHubService::class);
                                    $success = $githubService->addComment($task, $data['comment']);

                                    Notification::make()
                                        ->title($success ? 'Comment added to GitHub issue' : 'Failed to add comment')
                                        ->{$success ? 'success' : 'danger'}()
                                        ->send();
                                } catch (Exception $e) {
                                    Notification::make()
                                        ->title('Error: '.$e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            }
                        }),

                    Action::make('viewOnGitHub')
                        ->label('View on GitHub')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->visible(fn (array $arguments) => Task::find($arguments['record'])?->github_issue_url)
                        ->url(fn (array $arguments) => Task::find($arguments['record'])?->github_issue_url)
                        ->openUrlInNewTab(),
                ])
                    ->label('GitHub Actions')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray'),
            ]);
    }

    public function closeTaskModal(): void
    {
        $this->showTaskModal = false;
        $this->selectedTask = null;
    }
}

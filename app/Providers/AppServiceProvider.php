<?php

namespace App\Providers;

use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Forms\Components\Field;
use Filament\Tables\Actions\ExportBulkAction;
use Filament\Tables\Columns\Column;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        DB::prohibitDestructiveCommands(app()->isProduction());

        Model::unguard();

        Model::shouldBeStrict();

        URL::forceHttps(app()->isProduction());

        Vite::useAggressivePrefetching();

        // Register Task Observer for GitHub sync
        \App\Models\Task::observe(\App\Observers\TaskObserver::class);

        Gate::define('viewPulse', function (User $user) {
            return $user->email === config('app.admin_email');
        });

        CreateAction::configureUsing(function (CreateAction $action): void {
            $action->icon('heroicon-o-plus');
        });

        ImportAction::configureUsing(function (ImportAction $action): void {
            $action
                ->color('info')
                ->icon('heroicon-o-document-arrow-up')
                ->translateLabel();
        });

        ExportAction::configureUsing(function (ExportAction $action): void {
            $action
                ->color('primary')
                ->icon('heroicon-o-document-arrow-down')
                ->translateLabel();
        });

        ExportBulkAction::configureUsing(function (ExportBulkAction $action): void {
            $action
                ->color('primary')
                ->icon('heroicon-o-document-arrow-down')
                ->translateLabel();
        });

        Field::configureUsing(function (Field $field): void {
            $field->translateLabel();
        });

        Column::configureUsing(function (Column $column): void {
            $column->translateLabel();
        });
    }
}

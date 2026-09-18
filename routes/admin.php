<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Blog\PostController;
use App\Http\Controllers\Admin\Budget\DebtController;
use App\Http\Controllers\Admin\Budget\ExpenseController;
use App\Http\Controllers\Admin\Budget\IncomeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\General\CategoryController;
use App\Http\Controllers\Admin\Library\BookController;
use App\Http\Controllers\Admin\Library\PublisherController;
use App\Http\Controllers\Admin\Library\WriterController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\Work\ClientController;
use App\Http\Controllers\Admin\Work\InvoiceController;
use App\Http\Controllers\Admin\Work\ProjectController;
use App\Http\Controllers\Admin\Work\RepositoryController;
use App\Http\Controllers\Admin\Work\TaskBoardController;
use App\Http\Controllers\Admin\Work\WakaTimeSummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin'])
    ->prefix('app')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('profile', ProfileController::class)->name('profile');
        Route::get('search', SearchController::class)->name('search');
        // read-all first, so it is not captured by the parameterised route.
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::patch('notifications/{notification}', [NotificationController::class, 'read'])->name('notifications.read');
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

        Route::get('settings', [SettingsController::class, 'edit'])->name('settings');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

        // Declared before the parameterised route so it is not swallowed by it.
        Route::get('exports/download', [ExportController::class, 'download'])
            ->middleware('signed')
            ->name('exports.download');
        Route::post('exports/{resource}', [ExportController::class, 'store'])->name('exports.store');

        // Bulk routes come first so "posts/bulk" is not captured by "posts/{post}".
        Route::delete('posts/bulk', [PostController::class, 'bulkDestroy'])->name('posts.bulk-destroy');
        Route::resource('posts', PostController::class)->except(['show']);

        Route::delete('categories/bulk', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-destroy');
        Route::resource('categories', CategoryController::class)->except(['show']);

        Route::delete('publishers/bulk', [PublisherController::class, 'bulkDestroy'])->name('publishers.bulk-destroy');
        Route::resource('publishers', PublisherController::class)->except(['show']);

        Route::delete('writers/bulk', [WriterController::class, 'bulkDestroy'])->name('writers.bulk-destroy');
        Route::resource('writers', WriterController::class)->except(['show']);

        Route::delete('books/bulk', [BookController::class, 'bulkDestroy'])->name('books.bulk-destroy');
        Route::resource('books', BookController::class)->except(['show']);

        Route::delete('clients/bulk', [ClientController::class, 'bulkDestroy'])->name('clients.bulk-destroy');
        Route::resource('clients', ClientController::class)->except(['show']);

        Route::delete('projects/bulk', [ProjectController::class, 'bulkDestroy'])->name('projects.bulk-destroy');
        Route::resource('projects', ProjectController::class)->except(['show']);

        Route::delete('repositories/bulk', [RepositoryController::class, 'bulkDestroy'])->name('repositories.bulk-destroy');
        Route::resource('repositories', RepositoryController::class);

        Route::delete('invoices/bulk', [InvoiceController::class, 'bulkDestroy'])->name('invoices.bulk-destroy');
        Route::resource('invoices', InvoiceController::class)->except(['show']);

        Route::get('tasks/board', [TaskBoardController::class, 'index'])->name('tasks.board');
        Route::post('tasks', [TaskBoardController::class, 'store'])->name('tasks.store');
        Route::patch('tasks/{task}/move', [TaskBoardController::class, 'move'])->name('tasks.move');
        Route::post('tasks/{task}/sync-github', [TaskBoardController::class, 'syncToGitHub'])->name('tasks.sync-github');
        Route::put('tasks/{task}', [TaskBoardController::class, 'update'])->name('tasks.update');
        Route::delete('tasks/{task}', [TaskBoardController::class, 'destroy'])->name('tasks.destroy');

        // Read only: synced from the WakaTime API, never authored by hand.
        Route::get('waka-time-summaries', [WakaTimeSummaryController::class, 'index'])->name('waka-time-summaries.index');
        Route::get('waka-time-summaries/{wakaTimeSummary}', [WakaTimeSummaryController::class, 'show'])->name('waka-time-summaries.show');

        Route::delete('incomes/bulk', [IncomeController::class, 'bulkDestroy'])->name('incomes.bulk-destroy');
        Route::resource('incomes', IncomeController::class);

        Route::delete('expenses/bulk', [ExpenseController::class, 'bulkDestroy'])->name('expenses.bulk-destroy');
        Route::resource('expenses', ExpenseController::class)->except(['show']);

        Route::delete('debts/bulk', [DebtController::class, 'bulkDestroy'])->name('debts.bulk-destroy');
        Route::post('debts/{debt}/pay', [DebtController::class, 'pay'])->name('debts.pay');
        Route::resource('debts', DebtController::class)->except(['show']);
    });

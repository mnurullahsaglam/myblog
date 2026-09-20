<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Http\Controllers\Admin\Blog\PostController;
use App\Http\Controllers\Admin\Budget\BudgetLimitController;
use App\Http\Controllers\Admin\Budget\DebtController;
use App\Http\Controllers\Admin\Budget\ExpenseController;
use App\Http\Controllers\Admin\Budget\IncomeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ExportController;
use App\Http\Controllers\Admin\General\CategoryController;
use App\Http\Controllers\Admin\General\PeopleController;
use App\Http\Controllers\Admin\Library\BookController;
use App\Http\Controllers\Admin\Library\IsbnLookupController;
use App\Http\Controllers\Admin\Library\PublisherController;
use App\Http\Controllers\Admin\Library\WriterController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\Utilities\UtilityAccountController;
use App\Http\Controllers\Admin\Utilities\UtilityBillController;
use App\Http\Controllers\Admin\Work\ClientController;
use App\Http\Controllers\Admin\Work\CodingDashboardController;
use App\Http\Controllers\Admin\Work\InvoiceController;
use App\Http\Controllers\Admin\Work\ProjectController;
use App\Http\Controllers\Admin\Work\RepositoryController;
use App\Http\Controllers\Admin\Work\TaskBoardController;
use App\Http\Controllers\Admin\Work\WakaTimeSummaryController;
use App\Support\Features;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-panel'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('profile', [ProfileController::class, 'index'])->name('profile');
        Route::put('preferences', [ProfileController::class, 'updatePreferences'])->name('preferences.update');
        Route::get('search', SearchController::class)->name('search');
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::patch('notifications/{notification}', [NotificationController::class, 'read'])->name('notifications.read');
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');

        Route::get('exports/download', [ExportController::class, 'download'])
            ->middleware('signed')
            ->name('exports.download');
        Route::post('exports/{resource}', [ExportController::class, 'store'])->name('exports.store');

        Route::middleware('area:'.Area::Blog->value)->group(function (): void {
            Route::delete('posts/bulk', [PostController::class, 'bulkDestroy'])->name('posts.bulk-destroy');
            Route::patch('posts/bulk', [PostController::class, 'bulkUpdate'])->name('posts.bulk-update');
            Route::resource('posts', PostController::class)->except(['show']);
        });

        Route::middleware('area:'.Area::General->value)->group(function (): void {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

            Route::get('people', [PeopleController::class, 'index'])->name('people.index');
            Route::post('people', [PeopleController::class, 'store'])->name('people.store');
            Route::post('people/{invite}/reissue', [PeopleController::class, 'reissue'])->name('people.reissue');
            Route::delete('people/{invite}', [PeopleController::class, 'revoke'])->name('people.revoke');

            Route::delete('categories/bulk', [CategoryController::class, 'bulkDestroy'])->name('categories.bulk-destroy');
            Route::patch('categories/bulk', [CategoryController::class, 'bulkUpdate'])->name('categories.bulk-update');
            Route::resource('categories', CategoryController::class)->except(['show']);
        });

        Route::middleware('area:'.Area::Library->value)->group(function (): void {
            Route::delete('publishers/bulk', [PublisherController::class, 'bulkDestroy'])->name('publishers.bulk-destroy');
            Route::patch('publishers/bulk', [PublisherController::class, 'bulkUpdate'])->name('publishers.bulk-update');
            Route::resource('publishers', PublisherController::class)->except(['show']);

            Route::delete('writers/bulk', [WriterController::class, 'bulkDestroy'])->name('writers.bulk-destroy');
            Route::patch('writers/bulk', [WriterController::class, 'bulkUpdate'])->name('writers.bulk-update');
            Route::resource('writers', WriterController::class)->except(['show']);

            Route::delete('books/bulk', [BookController::class, 'bulkDestroy'])->name('books.bulk-destroy');
            Route::patch('books/bulk', [BookController::class, 'bulkUpdate'])->name('books.bulk-update');
            // Declared before the resource route so "books/isbn" is not captured by
            // "books/{book}".
            Route::post('books/isbn', [IsbnLookupController::class, 'store'])->name('books.isbn');
            Route::post('books/isbn/relation', [IsbnLookupController::class, 'relation'])->name('books.isbn-relation');
            Route::resource('books', BookController::class)->except(['show']);
        });

        Route::middleware('area:'.Area::Utilities->value)->group(function (): void {
            Route::delete('utility-accounts/bulk', [UtilityAccountController::class, 'bulkDestroy'])->name('utility-accounts.bulk-destroy');
            Route::patch('utility-accounts/bulk', [UtilityAccountController::class, 'bulkUpdate'])->name('utility-accounts.bulk-update');
            Route::resource('utility-accounts', UtilityAccountController::class)->except(['show']);

            Route::delete('utility-bills/bulk', [UtilityBillController::class, 'bulkDestroy'])->name('utility-bills.bulk-destroy');
            Route::patch('utility-bills/bulk', [UtilityBillController::class, 'bulkUpdate'])->name('utility-bills.bulk-update');
            Route::post('utility-bills/{utilityBill}/pay', [UtilityBillController::class, 'pay'])->name('utility-bills.pay');
            Route::resource('utility-bills', UtilityBillController::class)->except(['show']);
        });

        Route::middleware('area:'.Area::Work->value)->group(function (): void {
            Route::delete('clients/bulk', [ClientController::class, 'bulkDestroy'])->name('clients.bulk-destroy');
            Route::patch('clients/bulk', [ClientController::class, 'bulkUpdate'])->name('clients.bulk-update');
            Route::resource('clients', ClientController::class)->except(['show']);

            Route::delete('projects/bulk', [ProjectController::class, 'bulkDestroy'])->name('projects.bulk-destroy');
            Route::patch('projects/bulk', [ProjectController::class, 'bulkUpdate'])->name('projects.bulk-update');
            Route::resource('projects', ProjectController::class)->except(['show']);

            Route::delete('repositories/bulk', [RepositoryController::class, 'bulkDestroy'])->name('repositories.bulk-destroy');
            Route::patch('repositories/bulk', [RepositoryController::class, 'bulkUpdate'])->name('repositories.bulk-update');
            Route::resource('repositories', RepositoryController::class);

            Route::delete('invoices/bulk', [InvoiceController::class, 'bulkDestroy'])->name('invoices.bulk-destroy');
            Route::patch('invoices/bulk', [InvoiceController::class, 'bulkUpdate'])->name('invoices.bulk-update');
            Route::resource('invoices', InvoiceController::class)->except(['show']);

            Route::get('coding-dashboard', CodingDashboardController::class)->name('coding-dashboard');

            Route::get('tasks/board', [TaskBoardController::class, 'index'])->name('tasks.board');
            Route::post('tasks', [TaskBoardController::class, 'store'])->name('tasks.store');
            Route::patch('tasks/{task}/move', [TaskBoardController::class, 'move'])->name('tasks.move');
            Route::post('tasks/{task}/sync-github', [TaskBoardController::class, 'syncToGitHub'])->name('tasks.sync-github');
            Route::put('tasks/{task}', [TaskBoardController::class, 'update'])->name('tasks.update');
            Route::delete('tasks/{task}', [TaskBoardController::class, 'destroy'])->name('tasks.destroy');

            Route::get('waka-time-summaries', [WakaTimeSummaryController::class, 'index'])->name('waka-time-summaries.index');
            Route::get('waka-time-summaries/{wakaTimeSummary}', [WakaTimeSummaryController::class, 'show'])->name('waka-time-summaries.show');
        });

        Route::middleware('area:'.Area::Budget->value)->group(function (): void {
            Route::get('budget-limits', [BudgetLimitController::class, 'index'])
                ->middleware('feature:'.Features::BudgetLimits)
                ->name('budget-limits.index');

            Route::delete('incomes/bulk', [IncomeController::class, 'bulkDestroy'])->name('incomes.bulk-destroy');
            Route::patch('incomes/bulk', [IncomeController::class, 'bulkUpdate'])->name('incomes.bulk-update');
            Route::resource('incomes', IncomeController::class);

            Route::delete('expenses/bulk', [ExpenseController::class, 'bulkDestroy'])->name('expenses.bulk-destroy');
            Route::patch('expenses/bulk', [ExpenseController::class, 'bulkUpdate'])->name('expenses.bulk-update');
            Route::resource('expenses', ExpenseController::class)->except(['show']);

            Route::delete('debts/bulk', [DebtController::class, 'bulkDestroy'])->name('debts.bulk-destroy');
            Route::patch('debts/bulk', [DebtController::class, 'bulkUpdate'])->name('debts.bulk-update');
            Route::post('debts/{debt}/pay', [DebtController::class, 'pay'])->name('debts.pay');
            Route::resource('debts', DebtController::class)->except(['show']);
        });
    });

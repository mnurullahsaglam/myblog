<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Blog\PostController;
use App\Http\Controllers\Admin\Budget\DebtController;
use App\Http\Controllers\Admin\Budget\ExpenseController;
use App\Http\Controllers\Admin\Budget\IncomeController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\General\CategoryController;
use App\Http\Controllers\Admin\Library\BookController;
use App\Http\Controllers\Admin\Library\PublisherController;
use App\Http\Controllers\Admin\Library\WriterController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\Work\ClientController;
use App\Http\Controllers\Admin\Work\InvoiceController;
use App\Http\Controllers\Admin\Work\ProjectController;
use App\Http\Controllers\Admin\Work\RepositoryController;
use App\Http\Controllers\Admin\Work\WakaTimeSummaryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:access-admin'])
    ->prefix('app')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('profile', ProfileController::class)->name('profile');

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

<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Http\Controllers\Api\V1\Blog\PostController;
use App\Http\Controllers\Api\V1\Budget\DebtController;
use App\Http\Controllers\Api\V1\Budget\ExpenseController;
use App\Http\Controllers\Api\V1\Budget\IncomeController;
use App\Http\Controllers\Api\V1\General\CategoryController;
use App\Http\Controllers\Api\V1\Library\BookController;
use App\Http\Controllers\Api\V1\Library\PublisherController;
use App\Http\Controllers\Api\V1\Library\WriterController;
use App\Http\Controllers\Api\V1\TokenController;
use App\Http\Controllers\Api\V1\Utilities\UtilityAccountController;
use App\Http\Controllers\Api\V1\Utilities\UtilityBillController;
use App\Http\Controllers\Api\V1\Work\ClientController;
use App\Http\Controllers\Api\V1\Work\InvoiceController;
use App\Http\Controllers\Api\V1\Work\ProjectController;
use App\Http\Controllers\Api\V1\Work\RepositoryController;
use App\Http\Controllers\Api\V1\Work\WakaTimeSummaryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('tokens', [TokenController::class, 'store'])
        ->middleware('throttle:api-token')
        ->name('tokens.store');
});

Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

    Route::middleware('area:'.Area::Blog->value)->group(function (): void {
        Route::get('posts/schema', [PostController::class, 'schema'])->name('posts.schema');
        Route::apiResource('posts', PostController::class);
    });

    Route::middleware('area:'.Area::General->value)->group(function (): void {
        Route::get('categories/schema', [CategoryController::class, 'schema'])->name('categories.schema');
        Route::apiResource('categories', CategoryController::class);
    });

    Route::middleware('area:'.Area::Library->value)->group(function (): void {
        Route::get('books/schema', [BookController::class, 'schema'])->name('books.schema');
        Route::apiResource('books', BookController::class);
        Route::get('writers/schema', [WriterController::class, 'schema'])->name('writers.schema');
        Route::apiResource('writers', WriterController::class);
        Route::get('publishers/schema', [PublisherController::class, 'schema'])->name('publishers.schema');
        Route::apiResource('publishers', PublisherController::class);
    });

    Route::middleware('area:'.Area::Utilities->value)->group(function (): void {
        Route::get('utility-accounts/schema', [UtilityAccountController::class, 'schema'])->name('utility-accounts.schema');
        Route::apiResource('utility-accounts', UtilityAccountController::class);
        Route::get('utility-bills/schema', [UtilityBillController::class, 'schema'])->name('utility-bills.schema');
        Route::apiResource('utility-bills', UtilityBillController::class);
    });

    Route::middleware('area:'.Area::Work->value)->group(function (): void {
        Route::get('clients/schema', [ClientController::class, 'schema'])->name('clients.schema');
        Route::apiResource('clients', ClientController::class);
        Route::get('projects/schema', [ProjectController::class, 'schema'])->name('projects.schema');
        Route::apiResource('projects', ProjectController::class);
        Route::get('repositories/schema', [RepositoryController::class, 'schema'])->name('repositories.schema');
        Route::apiResource('repositories', RepositoryController::class);
        Route::get('invoices/schema', [InvoiceController::class, 'schema'])->name('invoices.schema');
        Route::apiResource('invoices', InvoiceController::class);
        Route::get('waka-time-summaries/schema', [WakaTimeSummaryController::class, 'schema'])->name('waka-time-summaries.schema');
        Route::get('waka-time-summaries', [WakaTimeSummaryController::class, 'index'])->name('waka-time-summaries.index');
        Route::get('waka-time-summaries/{wakaTimeSummary}', [WakaTimeSummaryController::class, 'show'])->name('waka-time-summaries.show');
    });

    Route::middleware('area:'.Area::Budget->value)->group(function (): void {
        Route::get('incomes/schema', [IncomeController::class, 'schema'])->name('incomes.schema');
        Route::apiResource('incomes', IncomeController::class);
        Route::get('expenses/schema', [ExpenseController::class, 'schema'])->name('expenses.schema');
        Route::apiResource('expenses', ExpenseController::class);
        Route::get('debts/schema', [DebtController::class, 'schema'])->name('debts.schema');
        Route::apiResource('debts', DebtController::class);
    });
});

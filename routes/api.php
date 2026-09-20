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
        Route::apiResource('posts', PostController::class);
    });

    Route::middleware('area:'.Area::General->value)->group(function (): void {
        Route::apiResource('categories', CategoryController::class);
    });

    Route::middleware('area:'.Area::Library->value)->group(function (): void {
        Route::apiResource('books', BookController::class);
        Route::apiResource('writers', WriterController::class);
        Route::apiResource('publishers', PublisherController::class);
    });

    Route::middleware('area:'.Area::Utilities->value)->group(function (): void {
        Route::apiResource('utility-accounts', UtilityAccountController::class);
        Route::apiResource('utility-bills', UtilityBillController::class);
    });

    Route::middleware('area:'.Area::Work->value)->group(function (): void {
        Route::apiResource('clients', ClientController::class);
        Route::apiResource('projects', ProjectController::class);
        Route::apiResource('repositories', RepositoryController::class);
        Route::apiResource('invoices', InvoiceController::class);
        Route::get('waka-time-summaries', [WakaTimeSummaryController::class, 'index'])->name('waka-time-summaries.index');
        Route::get('waka-time-summaries/{wakaTimeSummary}', [WakaTimeSummaryController::class, 'show'])->name('waka-time-summaries.show');
    });

    Route::middleware('area:'.Area::Budget->value)->group(function (): void {
        Route::apiResource('incomes', IncomeController::class);
        Route::apiResource('expenses', ExpenseController::class);
        Route::apiResource('debts', DebtController::class);
    });
});

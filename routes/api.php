<?php

declare(strict_types=1);

use App\Enums\Area;
use App\Http\Controllers\Api\V1\Budget\IncomeController;
use App\Http\Controllers\Api\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('tokens', [TokenController::class, 'store'])
        ->middleware('throttle:api-token')
        ->name('tokens.store');
});

Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

    Route::middleware('area:'.Area::Budget->value)->group(function (): void {
        Route::apiResource('incomes', IncomeController::class);
    });

});

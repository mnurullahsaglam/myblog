<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Enums\Area;
use App\Http\Controllers\Api\V1\TokenController;
use App\Support\Access\AccessProfile;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('tokens', [TokenController::class, 'store'])
        ->middleware('throttle:api-token')
        ->name('tokens.store');
});

Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function (): void {
    Route::delete('tokens/current', [TokenController::class, 'destroy'])->name('tokens.destroy');

    Route::get('profile-probe', fn (): array => [
        'areas' => array_map(fn (Area $area): string => $area->value, app(AccessProfile::class)->areas()),
        'abilities' => array_map(
            fn (Ability $ability): string => $ability->value,
            array_filter(
                Ability::cases(),
                fn (Ability $ability): bool => app(AccessProfile::class)->allows($ability),
            ),
        ),
    ])->name('profile-probe');
});

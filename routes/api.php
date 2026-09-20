<?php

declare(strict_types=1);

use App\Enums\Ability;
use App\Enums\Area;
use App\Support\Access\AccessProfile;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('v1')->name('api.v1.')->group(function (): void {
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

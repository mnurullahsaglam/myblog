<?php

declare(strict_types=1);

use App\Http\Controllers\BookController;
use App\Http\Controllers\WakaTimeOAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::resource('books', BookController::class);

// WakaTime OAuth bootstrap (one-time consent). Behind auth so only the logged-in admin can connect.
Route::middleware('auth')->group(function (): void {
    Route::get('wakatime/connect', [WakaTimeOAuthController::class, 'connect'])->name('wakatime.connect');
    Route::get('wakatime/callback', [WakaTimeOAuthController::class, 'callback'])->name('wakatime.callback');
});

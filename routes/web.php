<?php

declare(strict_types=1);

use App\Http\Controllers\BookController;
use App\Http\Controllers\WakaTimeOAuthController;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): Factory|View => view('welcome'));

Route::get('books', [BookController::class, 'index'])->name('books.index');

Route::middleware('auth')->group(function (): void {
    Route::get('wakatime/connect', [WakaTimeOAuthController::class, 'connect'])->name('wakatime.connect');
    Route::get('wakatime/callback', [WakaTimeOAuthController::class, 'callback'])->name('wakatime.callback');
});

<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Blog\PostController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\ProfileController;
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
    });

<?php

use App\Http\Controllers\BookController;
use App\Services\WakaTimeService;
use Illuminate\Support\Facades\Route;

Route::get('testing', function () {
    $service = new WakaTimeService();
    $service->authenticate();
});

Route::get('/', function () {
    return view('welcome');
});

Route::resource('books', BookController::class);

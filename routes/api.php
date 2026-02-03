<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HostController;
use App\Http\Controllers\Api\OperationController;

// Группа
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    //Route::get('/users', [UserController::class, 'index']);
    Route::get('/hosts', [HostController::class, 'index']);
});


Route::get('/hosts', [HostController::class, 'index']);
Route::post('/hosts', [HostController::class, 'store']);

Route::patch('/hosts/{host}/rename', [HostController::class, 'rename'])
    ->middleware('throttle:host-rename');

Route::get('/operations/{operation}', [OperationController::class, 'show']);

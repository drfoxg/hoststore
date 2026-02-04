<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\HostController;
use App\Http\Controllers\Api\OperationController;
use App\Http\Controllers\Api\AuthController;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/hosts', [HostController::class, 'index']);
Route::post('/hosts', [HostController::class, 'store']);

Route::get('/operations/{operation}', [OperationController::class, 'show']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::patch('/hosts/{host}/rename', [HostController::class, 'rename'])
        ->middleware('throttle:host-rename');

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/hosts', [HostController::class, 'index']);
        Route::patch('/hosts/{host}/rename', [HostController::class, 'rename'])
            ->middleware('throttle:host-rename');
    });
});

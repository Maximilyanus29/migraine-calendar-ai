<?php

use App\Http\Controllers\Api\AttackController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MetaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/meta/options', [MetaController::class, 'options']);

    Route::get('/attacks/last', [AttackController::class, 'last']);
    Route::get('/attacks', [AttackController::class, 'index']);
    Route::post('/attacks', [AttackController::class, 'store']);
    Route::get('/attacks/{attack}', [AttackController::class, 'show']);
    Route::put('/attacks/{attack}', [AttackController::class, 'update']);
    Route::delete('/attacks/{attack}', [AttackController::class, 'destroy']);
});

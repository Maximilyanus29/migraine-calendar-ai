<?php

use App\Http\Controllers\Api\AttackController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomTriggerController;
use App\Http\Controllers\Api\MetaController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/meta/options', [MetaController::class, 'options']);
    Route::get('/custom-options', [CustomTriggerController::class, 'index']);
    Route::post('/custom-options', [CustomTriggerController::class, 'store'])->middleware('throttle:custom-option-create');
    Route::get('/custom-triggers', [CustomTriggerController::class, 'index']);
    Route::post('/custom-triggers', [CustomTriggerController::class, 'store'])->middleware('throttle:custom-trigger-create');
    Route::get('/admin/custom-triggers', [CustomTriggerController::class, 'adminIndex']);
    Route::post('/admin/custom-triggers/{customTrigger}/approve', [CustomTriggerController::class, 'approve']);
    Route::post('/admin/custom-triggers/{customTrigger}/reject', [CustomTriggerController::class, 'reject']);

    Route::get('/attacks/last', [AttackController::class, 'last']);
    Route::get('/attacks', [AttackController::class, 'index']);
    Route::post('/attacks', [AttackController::class, 'store'])->middleware('throttle:attack-create');
    Route::get('/attacks/{attack}', [AttackController::class, 'show']);
    Route::put('/attacks/{attack}', [AttackController::class, 'update'])->middleware('throttle:attack-update');
    Route::delete('/attacks/{attack}', [AttackController::class, 'destroy'])->middleware('throttle:attack-delete');
});

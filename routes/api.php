<?php

declare(strict_types=1);

use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DeviceAccessController;
use App\Http\Controllers\DeviceControlController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\FarmController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:auth');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:auth');
        Route::post('google', [AuthController::class, 'google'])->middleware('throttle:auth');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/fcm-token', [FcmTokenController::class, 'update']);
        Route::delete('auth/fcm-token', [FcmTokenController::class, 'destroy']);

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/{notification}', [NotificationController::class, 'update']);
        Route::get('farms', [FarmController::class, 'index']);
        Route::get('farms/{farm}', [FarmController::class, 'show']);
        Route::get('farms/{farm}/dashboard', [FarmController::class, 'dashboard']);
        Route::get('farms/{farm}/tasks', [FarmController::class, 'tasks']);
        Route::post('farms/{farm}/tasks', [FarmController::class, 'storeTask']);
        Route::patch('farms/{farm}/tasks/{task}', [FarmController::class, 'updateTask']);
        Route::get('farms/{farm}/telemetry', [FarmController::class, 'telemetry']);
        Route::get('farms/{farm}/telemetry/latest', [FarmController::class, 'telemetry']);
        Route::get('farms/{farm}/irrigation', [FarmController::class, 'irrigation']);
        Route::get('farms/{farm}/usage', [FarmController::class, 'usage']);
        Route::get('farms/{farm}/ripeness', [FarmController::class, 'ripeness']);
        Route::get('farms/{farm}/logs', [FarmController::class, 'logs']);
        Route::post('farms/{farm}/logs', [FarmController::class, 'storeLog']);
        Route::get('farms/{farm}/harvests', [FarmController::class, 'harvests']);
        Route::post('farms/{farm}/harvests', [FarmController::class, 'storeHarvest']);
        Route::post('farms/{farm}/assistant/query', [FarmController::class, 'assistant']);
        Route::get('controls', [DeviceControlController::class, 'all']);

        Route::get('devices', [DeviceController::class, 'index']);
        Route::post('devices/claim', [DeviceController::class, 'claim'])->middleware('throttle:claims');
        Route::get('devices/{device}', [DeviceController::class, 'show']);

        Route::get('devices/{device}/users', [DeviceAccessController::class, 'index']);
        Route::post('devices/{device}/users', [DeviceAccessController::class, 'store'])->middleware('throttle:users');
        Route::delete('devices/{device}/users/{access}', [DeviceAccessController::class, 'destroy'])->middleware('throttle:users');

        Route::get('devices/{device}/controls', [DeviceControlController::class, 'index']);
        Route::post('devices/{device}/controls', [DeviceControlController::class, 'store'])->middleware('throttle:controls');
        Route::get('devices/{device}/controls/{control}', [DeviceControlController::class, 'show']);
    });
});

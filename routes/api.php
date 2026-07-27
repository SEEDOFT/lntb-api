<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\FcmTokenController;
use App\Http\Controllers\Api\Device\DeviceAccessController;
use App\Http\Controllers\Api\Device\DeviceControlController;
use App\Http\Controllers\Api\Device\DeviceController;
use App\Http\Controllers\Api\Notification\NotificationController;
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

        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/{notification}', [NotificationController::class, 'update']);

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

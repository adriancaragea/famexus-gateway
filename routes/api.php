<?php

use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\FamilyController;
use App\Http\Controllers\ForwardController;
use App\Http\Controllers\HelloController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MeController;
use Illuminate\Support\Facades\Route;

// All routes here are prefixed with /api. The React app first calls
// GET /sanctum/csrf-cookie, then sends the XSRF-TOKEN cookie back as X-XSRF-TOKEN.
Route::prefix('v1')->group(function () {
    Route::get('/ping', fn () => response()->json([
        'service' => 'gateway',
        'status' => 'ok',
    ]));

    // Signing up, in and out, and password reset.
    Route::prefix('auth')->group(function () {
        Route::post('/register', RegisterController::class)->middleware(['throttle:6,1', 'session']);
        Route::post('/login', [SessionController::class, 'store'])->middleware('session');
        Route::post('/logout', [SessionController::class, 'destroy'])->middleware(['auth:sanctum', 'session']);
        Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:6,1');
        Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:6,1');
    });

    // Invite links are opened before the person has an account.
    Route::middleware('throttle:30,1')->group(function () {
        Route::get('/invitations/{token}', [InvitationController::class, 'show']);
        Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->middleware('session');
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', MeController::class);

        Route::middleware('family')->group(function () {
            Route::get('/family', [FamilyController::class, 'show']);

            Route::middleware('family:family.manage')->group(function () {
                Route::post('/family/invitations', [InvitationController::class, 'store'])->middleware('throttle:20,1');
                Route::delete('/family/invitations/{invitation}', [InvitationController::class, 'destroy']);
            });

            // Hello from every service, shown on the dashboard.
            Route::get('/hello', HelloController::class);

            // Everything under /finance goes to Wallit as /api/v1/...
            Route::any('/finance/{path?}', ForwardController::class)
                ->where('path', '.*')
                ->defaults('service', 'wallit')
                ->defaults('prefix', 'finance');
        });
    });
});

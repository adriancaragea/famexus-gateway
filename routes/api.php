<?php

use App\Http\Controllers\HelloController;
use Illuminate\Support\Facades\Route;

// All routes here are prefixed with /api.
Route::prefix('v1')->group(function () {
    Route::get('/ping', fn () => response()->json([
        'service' => 'gateway',
        'status' => 'ok',
    ]));

    // Hello from every service, shown on the dashboard. Public until login
    // exists; then it moves behind auth:sanctum and passes user claims on.
    Route::get('/hello', HelloController::class);
});

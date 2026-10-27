<?php

use Illuminate\Support\Facades\Route;

// All routes here are prefixed with /api.
Route::prefix('v1')->group(function () {
    Route::get('/ping', fn () => response()->json([
        'service' => 'gateway',
        'status' => 'ok',
    ]));
});

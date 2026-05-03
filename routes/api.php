<?php

use App\Http\Controllers\Api\GameBaseController;
use App\Http\Controllers\Api\GenreController;
use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return response()->json([
        'message' => 'Hello from Laravel API'
    ]);
});

Route::apiResource('genres', GenreController::class);

Route::apiResource('game-base', GameBaseController::class);
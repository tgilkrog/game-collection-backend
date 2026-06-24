<?php

use App\Http\Controllers\Api\ConditionController;
use App\Http\Controllers\Api\GameBaseController;
use App\Http\Controllers\Api\GameCopyController;
use App\Http\Controllers\Api\GenreController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn(Request $request) => $request->user());

    Route::apiResource('home', HomeController::class);
    Route::apiResource('genres', GenreController::class);

    Route::get('game-base/search', [GameBaseController::class, 'search']);
    Route::apiResource('game-base', GameBaseController::class);

    Route::apiResource('game-copies', GameCopyController::class);
    Route::apiResource('conditions', ConditionController::class);
    Route::apiResource('platforms', PlatformController::class);
});
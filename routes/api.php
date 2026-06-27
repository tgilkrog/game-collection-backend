<?php

use App\Http\Controllers\Api\ConditionController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\GameBaseController;
use App\Http\Controllers\Api\GameCopyController;
use App\Http\Controllers\Api\GenreController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// Public auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/logout', [AuthController::class, 'logout']);

// Public read routes
Route::get('/feed', [GameCopyController::class, 'feed']);
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{user}', [UserController::class, 'show']);
Route::get('/users/{user}/game-copies', [UserController::class, 'gameCopies']);
Route::get('/users/{user}/wishlist', [WishlistController::class, 'index']);
Route::get('/users/{user}/stats', [UserController::class, 'stats']);
Route::get('/game-copies', [GameCopyController::class, 'index']);
Route::get('/game-copies/{gameCopy}', [GameCopyController::class, 'show']);
Route::get('/conditions', [ConditionController::class, 'index']);
Route::get('/game-base', [GameBaseController::class, 'index']);
Route::get('/game-base/search', [GameBaseController::class, 'search'])->middleware('auth:sanctum');
Route::get('/game-base/{gameBase}', [GameBaseController::class, 'show']);

// Auth-protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn(Request $request) => $request->user());
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::put('/users/{user}/password', [UserController::class, 'changePassword']);
    Route::post('/users/{user}/follow', [FollowController::class, 'store']);
    Route::delete('/users/{user}/follow', [FollowController::class, 'destroy']);

    Route::apiResource('home', HomeController::class);
    Route::apiResource('genres', GenreController::class);

    Route::post('/game-base', [GameBaseController::class, 'store']);
    Route::put('/game-base/{gameBase}', [GameBaseController::class, 'update']);
    Route::delete('/game-base/{gameBase}', [GameBaseController::class, 'destroy']);

    Route::post('/game-copies', [GameCopyController::class, 'store']);
    Route::put('/game-copies/{gameCopy}', [GameCopyController::class, 'update']);
    Route::delete('/game-copies/{gameCopy}', [GameCopyController::class, 'destroy']);

    Route::post('/wishlist', [WishlistController::class, 'store']);
    Route::delete('/wishlist/{gameBase}', [WishlistController::class, 'destroy']);

    Route::apiResource('conditions', ConditionController::class)->except(['index']);
    Route::apiResource('platforms', PlatformController::class);
});

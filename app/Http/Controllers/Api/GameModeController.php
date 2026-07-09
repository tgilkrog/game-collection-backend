<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameModeResource;
use App\Models\GameMode;

class GameModeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $gameModes = GameMode::orderBy('name')->get();

        return GameModeResource::collection($gameModes);
    }
}

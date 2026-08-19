<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameModeResource;
use App\Models\GameMode;
use Illuminate\Http\Request;

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

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:game_modes,slug',
        ]);

        $gameMode = GameMode::create($validated);

        return new GameModeResource($gameMode);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GameMode $gameMode)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:game_modes,slug,' . $gameMode->id,
        ]);

        $gameMode->update($validated);

        return new GameModeResource($gameMode);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GameMode $gameMode)
    {
        $gameMode->delete();

        return response()->noContent();
    }
}

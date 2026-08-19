<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlayerPerspectiveResource;
use App\Models\PlayerPerspective;
use Illuminate\Http\Request;

class PlayerPerspectiveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $playerPerspectives = PlayerPerspective::orderBy('name')->get();

        return PlayerPerspectiveResource::collection($playerPerspectives);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:player_perspectives,slug',
        ]);

        $playerPerspective = PlayerPerspective::create($validated);

        return new PlayerPerspectiveResource($playerPerspective);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PlayerPerspective $playerPerspective)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:255|unique:player_perspectives,slug,' . $playerPerspective->id,
        ]);

        $playerPerspective->update($validated);

        return new PlayerPerspectiveResource($playerPerspective);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PlayerPerspective $playerPerspective)
    {
        $playerPerspective->delete();

        return response()->noContent();
    }
}

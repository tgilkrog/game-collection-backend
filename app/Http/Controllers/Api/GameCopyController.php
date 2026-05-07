<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameCopyResource;
use App\Models\GameCopy;
use Illuminate\Http\Request;

class GameCopyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $gameCopies = GameCopy::orderBy('title')->get();

        return GameCopyResource::collection($gameCopies);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         $validated = $request->validate([
            'title' => 'required|string',
            'game_base_id' => 'required|exists:game_bases,id',
            'platform_id' => 'required|exists:platforms,id',
            'region' => 'nullable|string',
            'purchase_price' => 'nullable|numeric',
            'purchase_date' => 'nullable|date',
            'notes' => 'nullable|string',

            // nested parts
            'parts' => 'array',
            'parts.*.type' => 'required|string',
            'parts.*.condition_id' => 'required|exists:conditions,id',
            'parts.*.notes' => 'nullable|string',
        ]);

        $gameCopy = GameCopy::create($validated);

        if (isset($validated['parts'])) {
            $gameCopy->parts()->createMany($validated['parts']);
        }

        return response()->json(
            $gameCopy->load(['game', 'platform', 'parts.condition']),
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(GameCopy $gameCopy)
    {
        $gameCopy->load(['parts']);
        return new GameCopyResource($gameCopy);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

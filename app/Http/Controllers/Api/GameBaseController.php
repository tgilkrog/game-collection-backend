<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameBaseResource;
use App\Models\GameBase;
use Illuminate\Http\Request;

class GameBaseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $gameBases = GameBase::orderBy('title')->get();

        return GameBaseResource::collection($gameBases);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string',
            'release_year' => 'required|integer',
            'publisher' => 'nullable|string',
            'developer' => 'nullable|string',
            'description' => 'nullable|string',
            'cover_image' => 'nullable|image',
            'genres' => 'array'
        ]);

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('covers', 'public');
            $validated['cover_image'] = '/storage/' . $path;
        }

        $game = GameBase::create($validated);

        if (!empty($validated['genres'])) {
            $game->genres()->sync($validated['genres']);
        }

        return response()->json($game->load('genres'));
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameBaseResource;
use App\Models\Condition;
use App\Models\GameBase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
    public function show(GameBase $gameBase)
    {
        $gameBase->load(['genres', 'game_copies.parts.condition', 'game_copies.platform']);

        return new GameBaseResource($gameBase);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GameBase $gameBase)
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
                if ($gameBase->cover_image) {
                    $oldPath = str_replace('/storage/', '', $gameBase->cover_image);
                    Storage::disk('public')->delete($oldPath);
                }

                $path = $request->file('cover_image')->store('covers', 'public');
                $validated['cover_image'] = '/storage/' . $path;
            }

            $gameBase->update($validated);

            if (isset($validated['genres'])) {
                $gameBase->genres()->sync($validated['genres']);
            }

            return response()->json($gameBase->load('genres'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GameBase $gameBase)
    {
        $gameBase->delete();

        return response()->noContent();
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');

        $games = GameBase::with('game_copies.platform')
            ->where('title', 'like', "%{$query}%")
            ->limit(10)
            ->get()
            ->map(function ($game) {
                $game->cover_image = asset($game->cover_image);
                return $game;
            });

        return response()->json($games);
    }
}

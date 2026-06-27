<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameBaseListResource;
use App\Http\Resources\GameBaseResource;
use App\Models\GameBase;
use App\Services\IgdbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GameBaseController extends Controller
{
    public function __construct(private IgdbService $igdb) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $gameBases = GameBase::orderBy('title')->paginate(24);

        return GameBaseListResource::collection($gameBases);
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

        return response()->json($game->load(['genres', 'themes', 'gameModes', 'playerPerspectives']));
    }

    /**
     * Display the specified resource.
     */
    public function show(GameBase $gameBase)
    {
        $relations = ['genres', 'themes', 'gameModes', 'playerPerspectives'];

        if ($userId = auth()->id()) {
            $relations['game_copies'] = fn($q) => $q->where('user_id', $userId)
                                                     ->with(['parts.condition', 'platform']);
        }

        $gameBase->load($relations);

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

            return response()->json($gameBase->load(['genres', 'themes', 'gameModes', 'playerPerspectives']));
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
        $query  = $request->get('q', '');
        $source = $request->get('source');

        $local = GameBase::where('title', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn($game) => [
                'source'       => 'local',
                'id'           => $game->id,
                'igdb_id'      => $game->igdb_id,
                'title'        => $game->title,
                'cover_image'  => $game->cover_image,
                'release_year' => $game->release_year,
            ]);

        if ($source === 'local') {
            return response()->json($local->values());
        }

        $localIgdbIds = $local->pluck('igdb_id')->filter()->all();
        $localTitles  = $local->pluck('title')->map(fn($t) => strtolower($t))->all();

        $igdb = collect($this->igdb->search($query))
            ->filter(fn($game) => !in_array($game['igdb_id'], $localIgdbIds)
                               && !in_array(strtolower($game['title']), $localTitles))
            ->values();

        return response()->json($local->concat($igdb));
    }
}

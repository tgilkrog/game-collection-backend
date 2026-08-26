<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameBaseListResource;
use App\Http\Resources\GameBaseResource;
use App\Models\GameBase;
use App\Services\IgdbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GameBaseController extends Controller
{
    public function __construct(private IgdbService $igdb) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = GameBase::query();

        if ($request->filled('genre_id')) {
            $ids = (array) $request->input('genre_id');
            $query->whereHas('genres', fn ($q) => $q->whereIn('genres.id', $ids));
        }

        if ($request->filled('theme_id')) {
            $ids = (array) $request->input('theme_id');
            $query->whereHas('themes', fn ($q) => $q->whereIn('themes.id', $ids));
        }

        if ($request->filled('game_mode_id')) {
            $ids = (array) $request->input('game_mode_id');
            $query->whereHas('gameModes', fn ($q) => $q->whereIn('game_modes.id', $ids));
        }

        if ($request->filled('player_perspective_id')) {
            $ids = (array) $request->input('player_perspective_id');
            $query->whereHas('playerPerspectives', fn ($q) => $q->whereIn('player_perspectives.id', $ids));
        }

        if ($request->filled('platform_id')) {
            $ids = (array) $request->input('platform_id');
            $query->whereHas('game_copies', fn ($q) => $q->whereIn('game_copies.platform_id', $ids));
        }

        $gameBases = $query->orderBy('title')->paginate(24)->withQueryString();

        return GameBaseListResource::collection($gameBases);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'release_year' => 'required|integer',
            'publisher' => 'nullable|string|max:255',
            'developer' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'cover_image' => 'nullable|image',
            'genres' => 'array',
            'genres.*' => 'integer|exists:genres,id',
            'themes' => 'array',
            'themes.*' => 'integer|exists:themes,id',
            'game_modes' => 'array',
            'game_modes.*' => 'integer|exists:game_modes,id',
            'player_perspectives' => 'array',
            'player_perspectives.*' => 'integer|exists:player_perspectives,id',
        ]);

        if ($request->hasFile('cover_image')) {
            $path = $request->file('cover_image')->store('covers', 'public');
            $validated['cover_image'] = '/storage/'.$path;
        }

        $game = DB::transaction(function () use ($validated) {
            $game = GameBase::create($validated);

            if (! empty($validated['genres'])) {
                $game->genres()->sync($validated['genres']);
            }
            if (! empty($validated['themes'])) {
                $game->themes()->sync($validated['themes']);
            }
            if (! empty($validated['game_modes'])) {
                $game->gameModes()->sync($validated['game_modes']);
            }
            if (! empty($validated['player_perspectives'])) {
                $game->playerPerspectives()->sync($validated['player_perspectives']);
            }

            return $game;
        });

        return response()->json($game->load(['genres', 'themes', 'gameModes', 'playerPerspectives']));
    }

    /**
     * Display the specified resource.
     */
    public function show(GameBase $gameBase)
    {
        $userId = auth()->id();

        $gameBase->load(['genres', 'themes', 'gameModes', 'playerPerspectives']);

        $data = (new GameBaseResource($gameBase))->resolve();
        $data['is_wishlisted'] = $userId
            ? $gameBase->wishlists()->where('user_id', $userId)->exists()
            : false;

        return response()->json($data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GameBase $gameBase)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'release_year' => 'required|integer',
            'publisher' => 'nullable|string|max:255',
            'developer' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'cover_image' => 'nullable|image',
            'genres' => 'array',
            'genres.*' => 'integer|exists:genres,id',
            'themes' => 'array',
            'themes.*' => 'integer|exists:themes,id',
            'game_modes' => 'array',
            'game_modes.*' => 'integer|exists:game_modes,id',
            'player_perspectives' => 'array',
            'player_perspectives.*' => 'integer|exists:player_perspectives,id',
        ]);

        if ($request->hasFile('cover_image')) {
            if ($gameBase->cover_image) {
                $oldPath = str_replace('/storage/', '', $gameBase->cover_image);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('cover_image')->store('covers', 'public');
            $validated['cover_image'] = '/storage/'.$path;
        }

        DB::transaction(function () use ($gameBase, $validated) {
            $gameBase->update($validated);

            if (isset($validated['genres'])) {
                $gameBase->genres()->sync($validated['genres']);
            }
            if (isset($validated['themes'])) {
                $gameBase->themes()->sync($validated['themes']);
            }
            if (isset($validated['game_modes'])) {
                $gameBase->gameModes()->sync($validated['game_modes']);
            }
            if (isset($validated['player_perspectives'])) {
                $gameBase->playerPerspectives()->sync($validated['player_perspectives']);
            }
        });

        return response()->json($gameBase->load(['genres', 'themes', 'gameModes', 'playerPerspectives']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GameBase $gameBase)
    {
        abort_if($gameBase->game_copies()->exists(), 409, 'Cannot delete a game that has copies in the collection.');

        $gameBase->delete();

        return response()->noContent();
    }

    public function search(Request $request)
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:100',
            'source' => 'nullable|in:local,igdb',
        ]);

        $query = $validated['q'] ?? '';
        $source = $validated['source'] ?? null;

        $local = GameBase::where('title', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->map(fn ($game) => [
                'source' => 'local',
                'id' => $game->id,
                'igdb_id' => $game->igdb_id,
                'title' => $game->title,
                'cover_image' => $game->cover_image,
                'release_year' => $game->release_year,
            ]);

        if ($source === 'local') {
            return response()->json($local->values());
        }

        $localIgdbIds = $local->pluck('igdb_id')->filter()->all();
        $localTitles = $local->pluck('title')->map(fn ($t) => strtolower($t))->all();

        $igdb = collect($this->igdb->search($query))
            ->filter(fn ($game) => ! in_array($game['igdb_id'], $localIgdbIds)
                               && ! in_array(strtolower($game['title']), $localTitles))
            ->values();

        return response()->json($local->concat($igdb));
    }
}

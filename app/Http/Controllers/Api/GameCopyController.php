<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameCopyResource;
use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\GameMode;
use App\Models\Genre;
use App\Models\PlayerPerspective;
use App\Models\Theme;
use App\Services\IgdbService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GameCopyController extends Controller
{
    public function __construct(private IgdbService $igdb) {}

    public function feed()
    {
        $copies = GameCopy::with(['game', 'platform', 'user'])
            ->latest()
            ->paginate(10);

        return GameCopyResource::collection($copies);
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $gameCopies = GameCopy::with(['game', 'platform', 'user'])
            ->latest()
            ->paginate(24);

        return GameCopyResource::collection($gameCopies);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
         $validated = $request->validate([
            'title'        => 'nullable|string',
            'game_base_id' => 'required_without:igdb_id|exists:game_bases,id',
            'igdb_id'      => 'required_without:game_base_id|integer',
            'platform_id'  => 'required|exists:platforms,id',
            'region'       => 'nullable|string',
            'purchase_price' => 'nullable|numeric',
            'purchase_date'  => 'nullable|date',
            'notes'          => 'nullable|string',

            // nested parts
            'parts'               => 'array',
            'parts.*.type'        => 'required|string',
            'parts.*.condition_id' => 'required|exists:conditions,id',
            'parts.*.notes'       => 'nullable|string',
        ]);

        if ($request->filled('igdb_id')) {
            $igdbData = $this->igdb->find($request->igdb_id);
            $igdbGenres = $igdbData['genres'] ?? [];
            unset($igdbData['genres']);

            $gameBase = GameBase::where('igdb_id', $request->igdb_id)->first()
                ?? GameBase::firstOrCreate(
                    ['title' => $igdbData['title']],
                    $igdbData
                );

            if ($gameBase->wasRecentlyCreated) {
                if (!empty($igdbGenres)) {
                    $gameBase->genres()->sync(
                        collect($igdbGenres)->map(fn($g) => Genre::updateOrCreate(
                            ['slug' => Str::slug($g['name'])],
                            ['name' => $g['name'], 'igdb_id' => $g['id']]
                        )->id)->all()
                    );
                }

                if (!empty($igdbData['themes'])) {
                    $gameBase->themes()->sync(
                        collect($igdbData['themes'])->map(fn($t) => Theme::updateOrCreate(
                            ['slug' => Str::slug($t['name'])],
                            ['name' => $t['name'], 'igdb_id' => $t['id']]
                        )->id)->all()
                    );
                }

                if (!empty($igdbData['game_modes'])) {
                    $gameBase->gameModes()->sync(
                        collect($igdbData['game_modes'])->map(fn($m) => GameMode::updateOrCreate(
                            ['slug' => Str::slug($m['name'])],
                            ['name' => $m['name'], 'igdb_id' => $m['id']]
                        )->id)->all()
                    );
                }

                if (!empty($igdbData['player_perspectives'])) {
                    $gameBase->playerPerspectives()->sync(
                        collect($igdbData['player_perspectives'])->map(fn($p) => PlayerPerspective::updateOrCreate(
                            ['slug' => Str::slug($p['name'])],
                            ['name' => $p['name'], 'igdb_id' => $p['id']]
                        )->id)->all()
                    );
                }
            }

            $validated['game_base_id'] = $gameBase->id;
        }

        unset($validated['igdb_id']);
        $validated['user_id'] = auth()->id();

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
        $gameCopy->load([
            'game.genres',
            'game.themes',
            'game.gameModes',
            'game.playerPerspectives',
            'platform',
            'parts.condition',
            'user',
        ]);
        return new GameCopyResource($gameCopy);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, GameCopy $gameCopy)
    {
        abort_if($gameCopy->user_id !== auth()->id(), 403);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GameCopy $gameCopy)
    {
        abort_if($gameCopy->user_id !== auth()->id(), 403);
    }
}

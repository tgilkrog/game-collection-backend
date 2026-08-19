<?php

namespace App\Http\Controllers\Api;

use App\Exports\GameCopiesExport;
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
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class GameCopyController extends Controller
{
    public function __construct(private IgdbService $igdb) {}

    public function feed(Request $request)
    {
        $query = GameCopy::with(['game', 'platform', 'user'])->latest();

        if ($request->boolean('following') && auth()->check()) {
            $followingIds = auth()->user()->following()->pluck('users.id');
            $query->whereIn('user_id', $followingIds);
        }

        return GameCopyResource::collection($query->paginate(10));
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = GameCopy::with(['game', 'platform', 'user'])->latest();

        if ($request->filled('platform_id')) {
            $query->whereIn('platform_id', (array) $request->input('platform_id'));
        }

        if ($request->filled('condition_id')) {
            $ids = (array) $request->input('condition_id');
            $query->whereHas('parts', fn ($q) => $q->whereIn('condition_id', $ids));
        }

        if ($request->filled('genre_id')) {
            $ids = (array) $request->input('genre_id');
            $query->whereHas('game.genres', fn ($q) => $q->whereIn('genres.id', $ids));
        }

        if ($request->filled('theme_id')) {
            $ids = (array) $request->input('theme_id');
            $query->whereHas('game.themes', fn ($q) => $q->whereIn('themes.id', $ids));
        }

        if ($request->filled('game_mode_id')) {
            $ids = (array) $request->input('game_mode_id');
            $query->whereHas('game.gameModes', fn ($q) => $q->whereIn('game_modes.id', $ids));
        }

        if ($request->filled('player_perspective_id')) {
            $ids = (array) $request->input('player_perspective_id');
            $query->whereHas('game.playerPerspectives', fn ($q) => $q->whereIn('player_perspectives.id', $ids));
        }

        return GameCopyResource::collection($query->paginate(24)->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'game_base_id' => 'required_without:igdb_id|exists:game_bases,id',
            'igdb_id' => 'required_without:game_base_id|integer',
            'platform_id' => 'required|exists:platforms,id',
            'region' => 'nullable|string|max:255',
            'purchase_price' => 'nullable|numeric',
            'purchase_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',

            // nested parts
            'parts' => 'array',
            'parts.*.type' => 'required|string|max:100',
            'parts.*.condition_id' => 'required|exists:conditions,id',
            'parts.*.notes' => 'nullable|string|max:500',
        ]);

        if ($request->filled('igdb_id')) {
            $igdbData = $this->igdb->find($request->igdb_id);

            if (empty($igdbData)) {
                abort(422, 'Could not find that game on IGDB. It may have been removed, or IGDB is temporarily unavailable.');
            }

            $igdbGenres = $igdbData['genres'] ?? [];
            unset($igdbData['genres']);

            $gameBase = GameBase::where('igdb_id', $request->igdb_id)->first()
                ?? GameBase::firstOrCreate(
                    ['title' => $igdbData['title']],
                    $igdbData
                );

            if ($gameBase->wasRecentlyCreated) {
                if (! empty($igdbGenres)) {
                    $gameBase->genres()->sync(
                        collect($igdbGenres)->map(fn ($g) => Genre::updateOrCreate(
                            ['slug' => Str::slug($g['name'])],
                            ['name' => $g['name'], 'igdb_id' => $g['id']]
                        )->id)->all()
                    );
                }

                if (! empty($igdbData['themes'])) {
                    $gameBase->themes()->sync(
                        collect($igdbData['themes'])->map(fn ($t) => Theme::updateOrCreate(
                            ['slug' => Str::slug($t['name'])],
                            ['name' => $t['name'], 'igdb_id' => $t['id']]
                        )->id)->all()
                    );
                }

                if (! empty($igdbData['game_modes'])) {
                    $gameBase->gameModes()->sync(
                        collect($igdbData['game_modes'])->map(fn ($m) => GameMode::updateOrCreate(
                            ['slug' => Str::slug($m['name'])],
                            ['name' => $m['name'], 'igdb_id' => $m['id']]
                        )->id)->all()
                    );
                }

                if (! empty($igdbData['player_perspectives'])) {
                    $gameBase->playerPerspectives()->sync(
                        collect($igdbData['player_perspectives'])->map(fn ($p) => PlayerPerspective::updateOrCreate(
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

        $validated = $request->validate([
            'platform_id' => 'required|exists:platforms,id',
            'title' => 'nullable|string|max:255',
            'region' => 'nullable|string|max:255',
            'purchase_price' => 'nullable|numeric',
            'purchase_date' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'parts' => 'array',
            'parts.*.type' => 'required|string|max:100',
            'parts.*.condition_id' => 'required|exists:conditions,id',
            'parts.*.notes' => 'nullable|string|max:500',
        ]);

        $gameCopy->update($validated);

        if (array_key_exists('parts', $validated)) {
            $gameCopy->parts()->delete();
            $gameCopy->parts()->createMany($validated['parts']);
        }

        return new GameCopyResource($gameCopy->load(['game', 'platform', 'parts.condition']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GameCopy $gameCopy)
    {
        abort_if($gameCopy->user_id !== auth()->id(), 403);
        $gameCopy->delete();

        return response()->noContent();
    }

    public function export(Request $request)
    {
        $validated = $request->validate([
            'format' => 'required|in:xlsx,csv',
            'columns' => 'required|array|min:1',
            'columns.*' => 'in:'.implode(',', array_keys(GameCopiesExport::COLUMNS)),
        ]);

        $copies = GameCopy::where('user_id', auth()->id())
            ->with(['game', 'platform', 'parts.condition'])
            ->get();

        $writerType = $validated['format'] === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;

        return Excel::download(
            new GameCopiesExport($copies, $validated['columns']),
            "collection.{$validated['format']}",
            $writerType
        );
    }
}

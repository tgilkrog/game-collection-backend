<?php

namespace App\Http\Controllers\Api;

use App\Exports\GameCopiesExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\GameCopyResource;
use App\Models\GameBase;
use App\Models\GameCopy;
use App\Models\GameCopyReview;
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
        $query = GameCopy::with(['game', 'platform', 'user', 'review'])->latest();

        if ($request->boolean('following') && auth()->check()) {
            $followingIds = auth()->user()->following()->pluck('users.id');
            $query->whereIn('user_id', $followingIds);
        }

        return GameCopyResource::collection($query->paginate($request->integer('per_page', 18)));
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = GameCopy::with([
            'game', 'platform', 'parts.condition', 'review',
            'user' => fn ($q) => $q->withCount('gameCopies'),
        ])->latest();

        if ($request->filled('game_base_id')) {
            $query->whereIn('game_base_id', (array) $request->input('game_base_id'));
        }

        if ($request->filled('exclude_ids')) {
            $query->whereNotIn('id', (array) $request->input('exclude_ids'));
        }

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

        if ($request->filled('play_status')) {
            $ids = (array) $request->input('play_status');
            $query->whereHas('review', fn ($q) => $q->whereIn('play_status', $ids));
        }

        return GameCopyResource::collection($query->paginate(24)->withQueryString());
    }

    /**
     * Pick a random copy from the authenticated user's backlog.
     */
    public function randomBacklog()
    {
        $copy = GameCopy::where('user_id', auth()->id())
            ->whereHas('review', fn ($q) => $q->where('play_status', 'backlog'))
            ->with(['game', 'platform', 'review'])
            ->inRandomOrder()
            ->first();

        if (! $copy) {
            return response()->json(['message' => 'No backlog copies found.'], 404);
        }

        return new GameCopyResource($copy);
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
            'play_status' => 'nullable|in:'.implode(',', GameCopyReview::PLAY_STATUSES),
            'rating' => 'nullable|integer|between:1,5',
            'hours_played' => 'nullable|numeric|min:0|max:9999.9',
            'playthrough_count' => 'nullable|integer|min:0|max:999',
            'would_replay' => 'nullable|boolean',
            'would_recommend' => 'nullable|boolean',

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

        $reviewFields = [
            'play_status' => $validated['play_status'] ?? 'backlog',
            'rating' => $validated['rating'] ?? null,
            'hours_played' => $validated['hours_played'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'playthrough_count' => $validated['playthrough_count'] ?? null,
            'would_replay' => $validated['would_replay'] ?? null,
            'would_recommend' => $validated['would_recommend'] ?? null,
        ];
        unset(
            $validated['play_status'], $validated['rating'], $validated['hours_played'], $validated['notes'],
            $validated['playthrough_count'], $validated['would_replay'], $validated['would_recommend'],
        );

        $gameCopy = GameCopy::create($validated);

        if (isset($validated['parts'])) {
            $gameCopy->parts()->createMany($validated['parts']);
        }

        $gameCopy->review()->create(array_merge($reviewFields, [
            'user_id' => $gameCopy->user_id,
            'game_base_id' => $gameCopy->game_base_id,
        ]));

        return response()->json(
            $gameCopy->load(['game', 'platform', 'parts.condition', 'review']),
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
            'review',
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
            'play_status' => 'nullable|in:'.implode(',', GameCopyReview::PLAY_STATUSES),
            'rating' => 'nullable|integer|between:1,5',
            'hours_played' => 'nullable|numeric|min:0|max:9999.9',
            'playthrough_count' => 'nullable|integer|min:0|max:999',
            'would_replay' => 'nullable|boolean',
            'would_recommend' => 'nullable|boolean',
            'parts' => 'array',
            'parts.*.type' => 'required|string|max:100',
            'parts.*.condition_id' => 'required|exists:conditions,id',
            'parts.*.notes' => 'nullable|string|max:500',
        ]);

        $existingReview = $gameCopy->review;
        $reviewFields = [
            'play_status' => $validated['play_status'] ?? $existingReview?->play_status ?? 'backlog',
            'rating' => array_key_exists('rating', $validated) ? $validated['rating'] : $existingReview?->rating,
            'hours_played' => array_key_exists('hours_played', $validated) ? $validated['hours_played'] : $existingReview?->hours_played,
            'notes' => array_key_exists('notes', $validated) ? $validated['notes'] : $existingReview?->notes,
            'playthrough_count' => array_key_exists('playthrough_count', $validated) ? $validated['playthrough_count'] : $existingReview?->playthrough_count,
            'would_replay' => array_key_exists('would_replay', $validated) ? $validated['would_replay'] : $existingReview?->would_replay,
            'would_recommend' => array_key_exists('would_recommend', $validated) ? $validated['would_recommend'] : $existingReview?->would_recommend,
        ];
        unset(
            $validated['play_status'], $validated['rating'], $validated['hours_played'], $validated['notes'],
            $validated['playthrough_count'], $validated['would_replay'], $validated['would_recommend'],
        );

        $gameCopy->update($validated);

        $gameCopy->review()->updateOrCreate(
            ['game_copy_id' => $gameCopy->id],
            array_merge($reviewFields, [
                'user_id' => $gameCopy->user_id,
                'game_base_id' => $gameCopy->game_base_id,
            ])
        );

        if (array_key_exists('parts', $validated)) {
            $normalize = fn ($parts) => collect($parts)
                ->map(fn ($p) => [
                    'type' => $p['type'],
                    'condition_id' => (int) $p['condition_id'],
                    'notes' => $p['notes'] ?? '',
                ])
                ->sortBy('type')
                ->values()
                ->all();

            $incoming = $normalize($validated['parts']);
            $existing = $normalize($gameCopy->parts()->get(['type', 'condition_id', 'notes']));

            if ($incoming !== $existing) {
                $gameCopy->parts()->delete();
                $gameCopy->parts()->createMany($validated['parts']);
            }
        }

        return new GameCopyResource($gameCopy->load(['game', 'platform', 'parts.condition', 'review']));
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
            ->with(['game', 'platform', 'parts.condition', 'review'])
            ->get();

        $writerType = $validated['format'] === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;

        return Excel::download(
            new GameCopiesExport($copies, $validated['columns']),
            "collection.{$validated['format']}",
            $writerType
        );
    }
}

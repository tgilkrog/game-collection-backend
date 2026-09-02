<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameCopyResource;
use App\Models\User;
use App\Support\UserRank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->withCount('gameCopies as copy_count')
            ->orderByDesc('copy_count')
            ->paginate(24);

        return response()->json([
            'data' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'avatar' => $u->avatar,
                'copy_count' => $u->copy_count,
                'rank' => UserRank::fromCount($u->copy_count),
            ]),
            'meta' => [
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
            ],
        ]);
    }

    public function show(User $user)
    {
        $stats = DB::table('users')
            ->where('id', $user->id)
            ->selectRaw('
                (select count(*) from game_copies where game_copies.user_id = users.id) as copy_count,
                (select count(*) from wishlists where wishlists.user_id = users.id) as wishlist_count,
                (select coalesce(sum(purchase_price), 0) from game_copies where game_copies.user_id = users.id) as total_value,
                (select count(distinct platform_id) from game_copies where game_copies.user_id = users.id) as platform_count,
                (select count(*) from follows where follows.following_id = users.id) as followers_count,
                (select count(*) from follows where follows.follower_id = users.id) as following_count,
                (select round(avg(gcr.rating), 1) from game_copy_reviews gcr join game_copies gc on gc.id = gcr.game_copy_id where gc.user_id = users.id and gcr.rating is not null) as avg_rating
            ')
            ->first();

        $isFollowing = auth()->check()
            ? $user->followers()->where('follower_id', auth()->id())->exists()
            : false;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'banner' => $user->banner,
            'banner_position' => $user->banner_position,
            'bio' => $user->bio,
            'is_admin' => $user->is_admin,
            'copy_count' => (int) $stats->copy_count,
            'wishlist_count' => (int) $stats->wishlist_count,
            'total_value' => (float) $stats->total_value,
            'platform_count' => (int) $stats->platform_count,
            'followers_count' => (int) $stats->followers_count,
            'following_count' => (int) $stats->following_count,
            'is_following' => $isFollowing,
            'rank' => UserRank::fromCount((int) $stats->copy_count),
            'avg_rating' => $stats->avg_rating !== null ? (float) $stats->avg_rating : null,
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->id !== auth()->id(), 403);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:users,name,'.$user->id,
            'avatar' => 'sometimes|image|max:2048',
            'banner' => 'sometimes|image|max:4096',
            'banner_position' => 'sometimes|integer|min:0|max:100',
            'bio' => 'nullable|string|max:500',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
            }
            $validated['avatar'] = '/storage/'.$request->file('avatar')->store('avatars', 'public');
        }

        if ($request->hasFile('banner')) {
            if ($user->banner) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->banner));
            }
            $validated['banner'] = '/storage/'.$request->file('banner')->store('banners', 'public');
        }

        if ($request->has('banner_position')) {
            $validated['banner_position'] = (int) $request->banner_position;
        }

        $user->update($validated);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'banner' => $user->banner,
            'banner_position' => $user->banner_position,
            'bio' => $user->bio,
        ]);
    }

    public function changePassword(Request $request, User $user)
    {
        abort_if($user->id !== auth()->id(), 403);

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        return response()->noContent();
    }

    public function stats(User $user)
    {
        $byPlatform = $user->gameCopies()
            ->join('platforms', 'platforms.id', '=', 'game_copies.platform_id')
            ->selectRaw('platforms.name as name, count(*) as count, sum(game_copies.purchase_price) as value')
            ->groupBy('platforms.id', 'platforms.name')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name ?: 'Unknown',
                'count' => (int) $row->count,
                'value' => round((float) $row->value, 2),
            ])
            ->values();

        $byGenre = $user->gameCopies()
            ->join('game_bases', 'game_bases.id', '=', 'game_copies.game_base_id')
            ->join('game_genre', 'game_genre.game_base_id', '=', 'game_bases.id')
            ->join('genres', 'genres.id', '=', 'game_genre.genre_id')
            ->selectRaw('genres.name as name, count(*) as count')
            ->groupBy('genres.id', 'genres.name')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'count' => (int) $row->count])
            ->values();

        $byDecade = $user->gameCopies()
            ->join('game_bases', 'game_bases.id', '=', 'game_copies.game_base_id')
            ->selectRaw('floor(coalesce(game_bases.release_year, 0) / 10) * 10 as decade, count(*) as count')
            ->groupBy('decade')
            ->orderByRaw('decade = 0, decade')
            ->get()
            ->map(fn ($row) => [
                'decade' => $row->decade > 0 ? ((int) $row->decade).'s' : 'Unknown',
                'count' => (int) $row->count,
            ])
            ->values();

        $byGenreRating = $user->gameCopies()
            ->join('game_copy_reviews', 'game_copy_reviews.game_copy_id', '=', 'game_copies.id')
            ->join('game_bases', 'game_bases.id', '=', 'game_copies.game_base_id')
            ->join('game_genre', 'game_genre.game_base_id', '=', 'game_bases.id')
            ->join('genres', 'genres.id', '=', 'game_genre.genre_id')
            ->whereNotNull('game_copy_reviews.rating')
            ->selectRaw('genres.name as name, avg(game_copy_reviews.rating) as avg_rating, count(*) as count')
            ->groupBy('genres.id', 'genres.name')
            ->orderByDesc('avg_rating')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'avg_rating' => round((float) $row->avg_rating, 1),
                'count' => (int) $row->count,
            ])
            ->values();

        return response()->json(compact('byPlatform', 'byGenre', 'byDecade', 'byGenreRating'));
    }

    public function gameCopies(Request $request, User $user)
    {
        $query = $user->gameCopies()->with(['game', 'platform', 'review']);

        if ($request->filled('play_status')) {
            $ids = (array) $request->input('play_status');
            $query->whereHas('review', fn ($q) => $q->whereIn('play_status', $ids));
        }

        $copies = $query->orderBy('purchase_date', 'desc')->paginate(24);

        return GameCopyResource::collection($copies);
    }
}

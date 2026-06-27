<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameCopyResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->withCount('gameCopies as copy_count')
            ->orderByDesc('copy_count')
            ->paginate(24);

        return response()->json([
            'data' => $users->map(fn($u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'avatar'     => $u->avatar,
                'copy_count' => $u->copy_count,
            ]),
            'meta' => [
                'total'        => $users->total(),
                'last_page'    => $users->lastPage(),
                'current_page' => $users->currentPage(),
                'per_page'     => $users->perPage(),
            ],
        ]);
    }

    public function show(User $user)
    {
        $user->loadCount('gameCopies');

        $isFollowing = auth()->check()
            ? $user->followers()->where('follower_id', auth()->id())->exists()
            : false;

        return response()->json([
            'id'              => $user->id,
            'name'            => $user->name,
            'avatar'          => $user->avatar,
            'banner'          => $user->banner,
            'banner_position' => $user->banner_position,
            'bio'             => $user->bio,
            'copy_count'      => $user->game_copies_count,
            'total_value'     => (float) $user->gameCopies()->sum('purchase_price'),
            'platform_count'  => $user->gameCopies()->distinct('platform_id')->count('platform_id'),
            'followers_count' => $user->followers()->count(),
            'following_count' => $user->following()->count(),
            'is_following'    => $isFollowing,
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->id !== auth()->id(), 403);

        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255|unique:users,name,' . $user->id,
            'avatar'          => 'sometimes|image|max:2048',
            'banner'          => 'sometimes|image|max:4096',
            'banner_position' => 'sometimes|integer|min:0|max:100',
            'bio'             => 'nullable|string|max:500',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->avatar));
            }
            $validated['avatar'] = '/storage/' . $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->hasFile('banner')) {
            if ($user->banner) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $user->banner));
            }
            $validated['banner'] = '/storage/' . $request->file('banner')->store('banners', 'public');
        }

        if ($request->has('banner_position')) {
            $validated['banner_position'] = (int) $request->banner_position;
        }

        $user->update($validated);

        return response()->json([
            'id'              => $user->id,
            'name'            => $user->name,
            'avatar'          => $user->avatar,
            'banner'          => $user->banner,
            'banner_position' => $user->banner_position,
            'bio'             => $user->bio,
        ]);
    }

    public function changePassword(Request $request, User $user)
    {
        abort_if($user->id !== auth()->id(), 403);

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(['password' => Hash::make($request->password)]);

        return response()->noContent();
    }

    public function gameCopies(User $user)
    {
        $copies = $user->gameCopies()
            ->with(['game', 'platform'])
            ->latest()
            ->paginate(24);

        return GameCopyResource::collection($copies);
    }
}

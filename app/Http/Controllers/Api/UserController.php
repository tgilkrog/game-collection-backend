<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameCopyResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function show(User $user)
    {
        $user->loadCount('gameCopies');

        return response()->json([
            'id'              => $user->id,
            'name'            => $user->name,
            'avatar'          => $user->avatar,
            'banner'          => $user->banner,
            'banner_position' => $user->banner_position,
            'copy_count'      => $user->game_copies_count,
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
        ]);
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

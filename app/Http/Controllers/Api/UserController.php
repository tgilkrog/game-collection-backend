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
        return response()->json([
            'id'         => $user->id,
            'name'       => $user->name,
            'avatar'     => $user->avatar,
            'banner'     => $user->banner,
            'copy_count' => $user->gameCopies()->count(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->id !== auth()->id(), 403);

        $validated = $request->validate([
            'name'   => 'sometimes|string|max:255|unique:users,name,' . $user->id,
            'avatar' => 'sometimes|image|max:2048',
            'banner' => 'sometimes|image|max:4096',
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

        $user->update($validated);

        return response()->json([
            'id'     => $user->id,
            'name'   => $user->name,
            'avatar' => $user->avatar,
            'banner' => $user->banner,
        ]);
    }

    public function gameCopies(User $user)
    {
        $copies = $user->gameCopies()
            ->with(['game', 'platform'])
            ->latest()
            ->get();

        return GameCopyResource::collection($copies);
    }
}

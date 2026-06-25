<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameCopyResource;
use App\Models\User;

class UserController extends Controller
{
    public function show(User $user)
    {
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'avatar' => $user->avatar,
            'banner' => $user->banner,
            'copy_count' => $user->gameCopies()->count(),
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

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameBaseListResource;
use App\Models\GameBase;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(User $user)
    {
        $games = $user->wishlist()
            ->with('game')
            ->latest()
            ->paginate(24);

        return GameBaseListResource::collection($games->through(fn($w) => $w->game));
    }

    public function store(Request $request)
    {
        $request->validate([
            'game_base_id' => 'required|exists:game_bases,id',
        ]);

        Wishlist::firstOrCreate([
            'user_id'      => auth()->id(),
            'game_base_id' => $request->game_base_id,
        ]);

        return response()->noContent();
    }

    public function destroy(GameBase $gameBase)
    {
        Wishlist::where('user_id', auth()->id())
            ->where('game_base_id', $gameBase->id)
            ->delete();

        return response()->noContent();
    }
}

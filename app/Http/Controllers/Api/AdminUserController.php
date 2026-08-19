<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserRank;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%");
            }))
            ->withCount('gameCopies as copy_count')
            ->orderBy('name')
            ->paginate(24);

        return response()->json([
            'data' => $users->map(fn ($u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'avatar' => $u->avatar,
                'is_admin' => $u->is_admin,
                'is_banned' => $u->is_banned,
                'copy_count' => $u->copy_count,
                'rank' => UserRank::fromCount($u->copy_count),
                'created_at' => $u->created_at,
            ]),
            'meta' => [
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
            ],
        ]);
    }

    /**
     * Promote a user to admin.
     */
    public function promote(User $user)
    {
        $user->forceFill(['is_admin' => true])->save();

        return response()->json(['id' => $user->id, 'name' => $user->name, 'is_admin' => true]);
    }

    /**
     * Demote a user from admin.
     */
    public function demote(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'You cannot remove your own admin access.');

        $user->forceFill(['is_admin' => false])->save();

        return response()->json(['id' => $user->id, 'name' => $user->name, 'is_admin' => false]);
    }

    /**
     * Ban a user.
     */
    public function ban(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'You cannot ban your own account.');

        $user->forceFill(['is_banned' => true])->save();

        return response()->json(['id' => $user->id, 'name' => $user->name, 'is_banned' => true]);
    }

    /**
     * Unban a user.
     */
    public function unban(User $user)
    {
        $user->forceFill(['is_banned' => false])->save();

        return response()->json(['id' => $user->id, 'name' => $user->name, 'is_banned' => false]);
    }
}

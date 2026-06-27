<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;

class FollowController extends Controller
{
    public function store(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'You cannot follow yourself.');
        auth()->user()->following()->syncWithoutDetaching([$user->id]);
        return response()->noContent();
    }

    public function destroy(User $user)
    {
        auth()->user()->following()->detach($user->id);
        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameCopyReviewResource;
use App\Models\GameCopyReview;
use Illuminate\Http\Request;

class GameCopyReviewController extends Controller
{
    /**
     * List the authenticated user's reviews whose copy no longer exists.
     */
    public function history(Request $request)
    {
        $reviews = GameCopyReview::where('user_id', auth()->id())
            ->whereNull('game_copy_id')
            ->where(function ($query) {
                $query->whereNotNull('rating')
                    ->orWhereNotNull('hours_played')
                    ->orWhereNotNull('notes')
                    ->orWhere('play_status', '!=', 'backlog');
            })
            ->with('game')
            ->latest()
            ->paginate(24);

        return GameCopyReviewResource::collection($reviews);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(GameCopyReview $gameCopyReview)
    {
        abort_if($gameCopyReview->user_id !== auth()->id(), 403);
        $gameCopyReview->delete();

        return response()->noContent();
    }
}

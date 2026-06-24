<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameCopy;
use App\Models\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       $platformTotals = Platform::query()
            ->leftJoin('game_copies', function ($join) {
                $join->on('platforms.id', '=', 'game_copies.platform_id')
                     ->where('game_copies.user_id', auth()->id());
            })
            ->select(
                'platforms.id',
                'platforms.name',
                'platforms.alias',
                DB::raw('COUNT(game_copies.id) as total')
            )
            ->groupBy(
                'platforms.id',
                'platforms.name',
                'platforms.alias'
            )
            ->get();

        $totalCopies = GameCopy::where('user_id', auth()->id())->count();

        return response()->json([
            'total_copies' => $totalCopies,
            'platform_totals' => $platformTotals,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlayerPerspectiveResource;
use App\Models\PlayerPerspective;

class PlayerPerspectiveController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $playerPerspectives = PlayerPerspective::orderBy('name')->get();

        return PlayerPerspectiveResource::collection($playerPerspectives);
    }
}

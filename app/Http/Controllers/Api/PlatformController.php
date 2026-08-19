<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformResource;
use App\Models\Platform;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $platforms = Platform::withCount('copies')
            ->orderBy('name')
            ->get();

        return PlatformResource::collection($platforms);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'release_year' => 'nullable|integer',
        ]);

        $platform = Platform::create($validated);

        return new PlatformResource($platform->loadCount('copies'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Platform $platform)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'alias' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'release_year' => 'nullable|integer',
        ]);

        $platform->update($validated);

        return new PlatformResource($platform->loadCount('copies'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Platform $platform)
    {
        abort_if($platform->copies()->exists(), 409, 'Cannot delete a platform that has copies in the collection.');

        $platform->delete();

        return response()->noContent();
    }
}

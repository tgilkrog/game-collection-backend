<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ConditionResource;
use App\Models\Condition;
use Illuminate\Http\Request;

class ConditionController extends Controller
{
    /**
     * Display a listing of the resource.
     * test for auto deploy
     */
    public function index(Request $request)
    {
        $conditions = Condition::when($request->boolean('in_use'), fn ($q) => $q->whereHas('copyParts'))
            ->orderBy('id')->get();

        return ConditionResource::collection($conditions);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $condition = Condition::create($validated);

        return new ConditionResource($condition);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Condition $condition)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
        ]);

        $condition->update($validated);

        return new ConditionResource($condition);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Condition $condition)
    {
        abort_if($condition->copyParts()->exists(), 409, 'Cannot delete a condition that is currently assigned to collection items.');

        $condition->delete();

        return response()->noContent();
    }
}

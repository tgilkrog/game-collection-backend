<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BarcodeLookupService;
use Illuminate\Http\Request;

class BarcodeLookupController extends Controller
{
    public function __construct(private BarcodeLookupService $lookup) {}

    public function show(Request $request)
    {
        $validated = $request->validate([
            'barcode' => 'required|string|max:32',
        ]);

        $result = $this->lookup->lookup($validated['barcode']);

        return response()->json([
            'matched' => (bool) $result,
            'result'  => $result,
        ]);
    }
}

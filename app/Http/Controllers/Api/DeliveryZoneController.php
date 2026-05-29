<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeliveryZoneController extends Controller
{
    public function index(): JsonResponse
    {
        $zones = DeliveryZone::all();
        return response()->json($zones);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'district' => 'required|string|max:255',
            'cost_motorizado' => 'required|numeric|min:0',
            'cost_shalom' => 'required|numeric|min:0',
        ]);

        $zone = DeliveryZone::create($validated);
        return response()->json($zone, 201);
    }

    public function show(string $id): JsonResponse
    {
        $zone = DeliveryZone::findOrFail($id);
        return response()->json($zone);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $zone = DeliveryZone::findOrFail($id);

        $validated = $request->validate([
            'district' => 'sometimes|string|max:255',
            'cost_motorizado' => 'sometimes|numeric|min:0',
            'cost_shalom' => 'sometimes|numeric|min:0',
        ]);

        $zone->update($validated);
        return response()->json($zone);
    }

    public function destroy(string $id): JsonResponse
    {
        $zone = DeliveryZone::findOrFail($id);
        $zone->delete();
        return response()->json(null, 204);
    }
}

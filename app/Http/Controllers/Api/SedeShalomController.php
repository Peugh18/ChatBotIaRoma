<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SedeShalom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SedeShalomController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            SedeShalom::query()->orderBy('nombre')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'region' => 'nullable|string|in:lima,provincia',
            'costo' => 'required|numeric|min:0',
            'activo' => 'nullable|boolean',
        ]);

        $sede = SedeShalom::create([
            ...$validated,
            'region' => $validated['region'] ?? 'provincia',
            'activo' => $validated['activo'] ?? true,
        ]);

        return response()->json($sede, 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $sede = SedeShalom::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'ciudad' => 'nullable|string|max:255',
            'region' => 'sometimes|string|in:lima,provincia',
            'costo' => 'sometimes|numeric|min:0',
            'activo' => 'nullable|boolean',
        ]);

        $sede->update($validated);

        return response()->json($sede);
    }

    public function destroy(string $id): JsonResponse
    {
        SedeShalom::findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}

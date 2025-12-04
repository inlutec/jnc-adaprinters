<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProvinceController extends Controller
{
    public function index(): JsonResponse
    {
        $provinces = Province::withCount('sites')->orderBy('name')->get();

        return response()->json($provinces);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:provinces,name'],
            'code' => ['sometimes', 'nullable', 'string', 'max:10', 'unique:provinces,code'],
        ]);

        $province = Province::create($validated);

        return response()->json($province, 201);
    }

    public function show(Province $province): JsonResponse
    {
        $province->load(['sites.departments']);

        return response()->json($province);
    }

    public function update(Request $request, Province $province): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:provinces,name,' . $province->id],
            'code' => ['sometimes', 'nullable', 'string', 'max:10', 'unique:provinces,code,' . $province->id],
        ]);

        $province->update($validated);

        return response()->json([
            'message' => __('Provincia actualizada'),
            'data' => $province->fresh(),
        ]);
    }

    public function destroy(Province $province): JsonResponse
    {
        // Verificar que no tenga sedes asociadas
        if ($province->sites()->count() > 0) {
            return response()->json([
                'message' => __('No se puede eliminar una provincia con sedes asociadas'),
            ], 422);
        }

        $province->delete();

        return response()->json(['message' => __('Provincia eliminada')]);
    }
}

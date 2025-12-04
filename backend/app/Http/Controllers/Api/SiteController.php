<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Site::with('province');

        if ($request->filled('province_id')) {
            $query->where('province_id', $request->integer('province_id'));
        }

        $sites = $query->orderBy('name')->get();

        return response()->json($sites);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'province_id' => ['required', 'exists:provinces,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:25', 'unique:sites,code'],
            'address' => ['sometimes', 'nullable', 'string'],
            'city' => ['sometimes', 'nullable', 'string'],
            'postal_code' => ['sometimes', 'nullable', 'string'],
            'latitude' => ['sometimes', 'nullable', 'numeric'],
            'longitude' => ['sometimes', 'nullable', 'numeric'],
            'contact_email' => ['sometimes', 'nullable', 'email'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
        ]);

        $site = Site::create($validated);

        return response()->json($site->load('province'), 201);
    }

    public function show(Site $site): JsonResponse
    {
        $site->load(['province', 'departments']);

        return response()->json($site);
    }

    public function update(Request $request, Site $site): JsonResponse
    {
        $validated = $request->validate([
            'province_id' => ['sometimes', 'exists:provinces,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:25', 'unique:sites,code,' . $site->id],
            'address' => ['sometimes', 'nullable', 'string'],
            'city' => ['sometimes', 'nullable', 'string'],
            'postal_code' => ['sometimes', 'nullable', 'string'],
            'latitude' => ['sometimes', 'nullable', 'numeric'],
            'longitude' => ['sometimes', 'nullable', 'numeric'],
            'contact_email' => ['sometimes', 'nullable', 'email'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $site->update($validated);

        return response()->json([
            'message' => __('Sede actualizada'),
            'data' => $site->fresh(['province']),
        ]);
    }

    public function destroy(Site $site): JsonResponse
    {
        // Verificar que no tenga departamentos asociados
        if ($site->departments()->count() > 0) {
            return response()->json([
                'message' => __('No se puede eliminar una sede con departamentos asociados'),
            ], 422);
        }

        $site->delete();

        return response()->json(['message' => __('Sede eliminada')]);
    }
}

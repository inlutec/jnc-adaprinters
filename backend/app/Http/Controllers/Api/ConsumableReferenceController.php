<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableReferenceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ConsumableReference::query();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('sku', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%")
                    ->orWhere('brand', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('brand')) {
            $query->where('brand', $request->string('brand'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $perPage = $request->integer('per_page', 15);
        $references = $query->orderBy('brand')->orderBy('name')->paginate($perPage);

        return response()->json($references);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sku' => ['required', 'string', 'max:255', 'unique:consumable_references,sku'],
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['sometimes', 'nullable', 'string'],
            'type' => ['required', 'string', 'in:Toner,Cartucho,Otro'],
            'custom_type' => ['required_if:type,Otro', 'nullable', 'string', 'max:255'],
            'color' => ['required_if:type,Toner,Cartucho', 'nullable', 'string', 'in:Negro,Cyan,Magenta,Amarillo'],
            'compatible_models' => ['sometimes', 'nullable', 'array'],
            'description' => ['sometimes', 'nullable', 'string'],
            'minimum_quantity' => ['sometimes', 'integer', 'min:0'],
        ]);

        $reference = ConsumableReference::create($validated);

        return response()->json($reference, 201);
    }

    public function show(ConsumableReference $consumableReference): JsonResponse
    {
        return response()->json($consumableReference);
    }

    public function update(Request $request, ConsumableReference $consumableReference): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'brand' => ['sometimes', 'nullable', 'string'],
            'type' => ['sometimes', 'string', 'in:Toner,Cartucho,Otro'],
            'custom_type' => ['required_if:type,Otro', 'nullable', 'string', 'max:255'],
            'color' => ['required_if:type,Toner,Cartucho', 'nullable', 'string', 'in:Negro,Cyan,Magenta,Amarillo'],
            'compatible_models' => ['sometimes', 'nullable', 'array'],
            'description' => ['sometimes', 'nullable', 'string'],
            'minimum_quantity' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $consumableReference->update($validated);

        return response()->json([
            'message' => __('Referencia actualizada'),
            'data' => $consumableReference->fresh(),
        ]);
    }

    public function movements(ConsumableReference $consumableReference): JsonResponse
    {
        $movements = $consumableReference->stock_movements;

        return response()->json($movements);
    }

    public function destroy(ConsumableReference $consumableReference): JsonResponse
    {
        $consumableReference->delete();

        return response()->json(['message' => __('Referencia eliminada')]);
    }
}

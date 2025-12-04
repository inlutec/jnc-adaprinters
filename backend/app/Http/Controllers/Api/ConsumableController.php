<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Consumable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Consumable::query();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder->where('name', 'ilike', "%{$search}%")
                    ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }

        $consumables = $query->orderBy('name')->paginate($request->integer('per_page', 15));

        return response()->json($consumables);
    }

    public function store(Request $request): JsonResponse
    {
        $consumable = Consumable::create($this->validatedData($request));

        return response()->json($consumable, 201);
    }

    public function show(Consumable $consumable): JsonResponse
    {
        $consumable->load('stocks');

        return response()->json($consumable);
    }

    public function update(Request $request, Consumable $consumable): JsonResponse
    {
        $consumable->update($this->validatedData($request, update: true));

        return response()->json($consumable->fresh('stocks'));
    }

    public function destroy(Consumable $consumable): JsonResponse
    {
        abort_if($consumable->stocks()->exists(), 422, __('Consumable has stock records'));

        $consumable->delete();

        return response()->json([
            'message' => __('Consumable deleted'),
        ]);
    }

    private function validatedData(Request $request, bool $update = false): array
    {
        return $request->validate([
            'name' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:100'],
            'brand' => ['nullable', 'string', 'max:100'],
            'color' => ['nullable', 'string', 'max:50'],
            'is_color' => ['boolean'],
            'average_yield' => ['nullable', 'integer', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'compatible_models' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);
    }
}


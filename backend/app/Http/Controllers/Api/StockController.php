<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Stock::query()
            ->with(['consumable', 'site', 'department'])
            ->whereHas('consumable', function ($q) {
                // Solo mostrar stocks de consumibles que tienen una referencia asociada (mismo SKU)
                $q->whereIn('sku', function ($subQuery) {
                    $subQuery->select('sku')
                        ->from('consumable_references')
                        ->where('is_active', true);
                });
            });

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->integer('site_id'));
        }

        if ($request->boolean('low_only')) {
            $query->whereColumn('quantity', '<=', 'minimum_quantity');
        }

        $stocks = $query->paginate($request->integer('per_page', 15));

        return response()->json($stocks);
    }

    public function adjust(Request $request, Stock $stock): JsonResponse
    {
        $payload = $request->validate([
            'movement_type' => ['required', 'in:in,out,adjustment'],
            'quantity' => ['required', 'integer'],
            'note' => ['nullable', 'string'],
        ]);

        $delta = (int) $payload['quantity'];
        if ($payload['movement_type'] === 'out') {
            $delta = -abs($delta);
        }

        $stock->quantity += $delta;
        $stock->save();

        $movement = $stock->movements()->create([
            'movement_type' => $payload['movement_type'],
            'quantity' => $payload['quantity'],
            'note' => $payload['note'] ?? null,
            'performed_by' => $request->user()?->id,
        ]);

        return response()->json([
            'stock' => $stock->fresh(['consumable', 'site', 'department']),
            'movement' => $movement,
        ]);
    }

    public function regularize(Request $request, Stock $stock): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
            'justification' => ['sometimes', 'nullable', 'string'],
        ]);

        $oldQuantity = $stock->quantity;
        $newQuantity = $validated['quantity'];
        $difference = $newQuantity - $oldQuantity;

        $stock->update(['quantity' => $newQuantity]);

        // Registrar movimiento de regularización
        $stock->movements()->create([
            'movement_type' => 'adjustment',
            'quantity' => abs($difference),
            'note' => "Regularización de inventario. Cantidad anterior: {$oldQuantity}, nueva: {$newQuantity}. " . ($validated['justification'] ?? ''),
            'performed_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => __('Stock regularizado'),
            'stock' => $stock->fresh(['consumable', 'site', 'department']),
        ]);
    }

    public function storeMovement(Request $request, Stock $stock): JsonResponse
    {
        $data = $request->validate([
            'movement_type' => ['required', 'in:in,out,adjustment'],
            'quantity' => ['required', 'integer'],
            'note' => ['nullable', 'string'],
            'justification' => ['sometimes', 'nullable', 'string'],
            'reference_type' => ['nullable', 'string', 'max:255'],
            'reference_id' => ['nullable', 'integer'],
        ]);

        // Actualizar cantidad del stock
        $delta = (int) $data['quantity'];
        if ($data['movement_type'] === 'out') {
            $delta = -abs($delta);
        } elseif ($data['movement_type'] === 'adjustment') {
            // Para ajustes, la cantidad es la nueva cantidad total
            $stock->quantity = $delta;
            $stock->save();
            $delta = 0; // No aplicar delta adicional
        } else {
            $stock->quantity += $delta;
            $stock->save();
        }

        $note = $data['note'] ?? '';
        if (isset($data['justification'])) {
            $note .= ($note ? '. ' : '') . "Justificación: {$data['justification']}";
        }

        $movement = $stock->movements()->create([
            'movement_type' => $data['movement_type'],
            'quantity' => abs((int) $data['quantity']),
            'note' => $note,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'performed_by' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => __('Movimiento registrado'),
            'stock' => $stock->fresh(['consumable', 'site', 'department']),
            'movement' => $movement,
        ], 201);
    }

    public function updateMinimumQuantity(Request $request, Stock $stock): JsonResponse
    {
        $validated = $request->validate([
            'minimum_quantity' => ['required', 'integer', 'min:0'],
        ]);

        $stock->update(['minimum_quantity' => $validated['minimum_quantity']]);

        return response()->json([
            'message' => __('Cantidad mínima actualizada'),
            'stock' => $stock->fresh(['consumable', 'site', 'department']),
        ]);
    }
}


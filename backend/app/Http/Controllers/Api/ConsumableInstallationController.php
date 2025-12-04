<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumableInstallation;
use App\Models\ConsumableInstallationPhoto;
use App\Models\Stock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ConsumableInstallationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ConsumableInstallation::with(['printer', 'stock.consumable', 'installer', 'photos']);

        if ($request->filled('printer_id')) {
            $query->where('printer_id', $request->integer('printer_id'));
        }

        if ($request->filled('stock_id')) {
            $query->where('stock_id', $request->integer('stock_id'));
        }

        $perPage = $request->integer('per_page', 15);
        $installations = $query->latest('installed_at')->paginate($perPage);

        return response()->json($installations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'printer_id' => ['required', 'exists:printers,id'],
            'stock_id' => ['required', 'exists:stocks,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'observations' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'installed_at' => ['sometimes', 'nullable', 'date'],
            'photos' => ['sometimes', 'nullable', 'array'],
            'photos.*' => ['image', 'max:20480'], // 20MB max
        ]);

        return DB::transaction(function () use ($request, $validated) {
            // Verificar que hay suficiente stock
            $stock = Stock::findOrFail($validated['stock_id']);
            if ($stock->quantity < $validated['quantity']) {
                return response()->json([
                    'message' => __('Stock insuficiente. Disponible: :available', ['available' => $stock->quantity]),
                ], 422);
            }

            // Crear la instalación
            $installation = ConsumableInstallation::create([
                'printer_id' => $validated['printer_id'],
                'stock_id' => $validated['stock_id'],
                'quantity' => $validated['quantity'],
                'observations' => $validated['observations'] ?? null,
                'installed_by' => auth()->id(),
                'installed_at' => $validated['installed_at'] ?? now(),
            ]);

            // Descontar del stock
            $stock->decrement('quantity', $validated['quantity']);

            // Registrar movimiento de stock
            $stock->movements()->create([
                'movement_type' => 'out',
                'quantity' => $validated['quantity'],
                'note' => "Instalación en impresora #{$installation->printer_id} (Instalación #{$installation->id})",
                'performed_by' => auth()->id(),
            ]);

            // Guardar fotos si se proporcionaron
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('installations', 'public');
                    ConsumableInstallationPhoto::create([
                        'consumable_installation_id' => $installation->id,
                        'photo_path' => $path,
                        'mime_type' => $photo->getMimeType(),
                    ]);
                }
            }

            return response()->json(
                $installation->load(['printer', 'stock.consumable', 'installer', 'photos']),
                201
            );
        });
    }

    public function show(ConsumableInstallation $installation): JsonResponse
    {
        // Cargar todas las relaciones - usar with() en lugar de load() para mejor control
        $installation->load([
            'printer:id,name,ip_address,brand,model',
            'stock:id,consumable_id,quantity,site_id,department_id',
            'stock.consumable:id,name,sku,brand',
            'installer:id,name,email',
            'photos:id,consumable_installation_id,photo_path,mime_type'
        ]);

        // Log para depuración
        Log::info('Installation show', [
            'id' => $installation->id,
            'printer_id' => $installation->printer_id,
            'stock_id' => $installation->stock_id,
            'installed_by' => $installation->installed_by,
            'printer_exists' => $installation->printer !== null,
            'stock_exists' => $installation->stock !== null,
            'installer_exists' => $installation->installer !== null,
        ]);

        return response()->json($installation);
    }

    public function update(Request $request, ConsumableInstallation $installation): JsonResponse
    {
        $validated = $request->validate([
            'printer_id' => ['sometimes', 'required', 'exists:printers,id'],
            'stock_id' => ['sometimes', 'required', 'exists:stocks,id'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'observations' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'installed_at' => ['sometimes', 'nullable', 'date'],
            'photos' => ['sometimes', 'nullable', 'array'],
            'photos.*' => ['image', 'max:20480'], // 20MB max
        ]);

        return DB::transaction(function () use ($request, $validated, $installation) {
            $oldQuantity = $installation->quantity;
            $oldStockId = $installation->stock_id;
            $newQuantity = $validated['quantity'] ?? $oldQuantity;
            $newStockId = $validated['stock_id'] ?? $oldStockId;

            // Si cambió la cantidad o el stock, manejar el stock
            if (isset($validated['quantity']) || isset($validated['stock_id'])) {
                // Si cambió el stock, restaurar el stock anterior y descontar del nuevo
                if ($oldStockId !== $newStockId) {
                    $oldStock = Stock::findOrFail($oldStockId);
                    $oldStock->increment('quantity', $oldQuantity);
                    
                    $newStock = Stock::findOrFail($newStockId);
                    if ($newStock->quantity < $newQuantity) {
                        return response()->json([
                            'message' => __('Stock insuficiente. Disponible: :available', ['available' => $newStock->quantity]),
                        ], 422);
                    }
                    $newStock->decrement('quantity', $newQuantity);
                } elseif (isset($validated['quantity']) && $oldQuantity !== $newQuantity) {
                    // Solo cambió la cantidad, ajustar el stock
                    $stock = $installation->stock;
                    $difference = $newQuantity - $oldQuantity;
                    
                    if ($difference > 0) {
                        // Aumentó la cantidad, verificar stock disponible
                        if ($stock->quantity < $difference) {
                            return response()->json([
                                'message' => __('Stock insuficiente. Disponible: :available', ['available' => $stock->quantity]),
                            ], 422);
                        }
                        $stock->decrement('quantity', $difference);
                    } else {
                        // Disminuyó la cantidad, restaurar stock
                        $stock->increment('quantity', abs($difference));
                    }
                }
            }

            // Actualizar la instalación
            $installation->update([
                'printer_id' => $validated['printer_id'] ?? $installation->printer_id,
                'stock_id' => $newStockId,
                'quantity' => $newQuantity,
                'observations' => $validated['observations'] ?? $installation->observations,
                'installed_at' => isset($validated['installed_at']) 
                    ? ($validated['installed_at'] ?: now()) 
                    : $installation->installed_at,
            ]);

            // Manejar nuevas fotos si se proporcionaron
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $path = $photo->store('installations', 'public');
                    ConsumableInstallationPhoto::create([
                        'consumable_installation_id' => $installation->id,
                        'photo_path' => $path,
                        'mime_type' => $photo->getMimeType(),
                    ]);
                }
            }

            return response()->json(
                $installation->load(['printer', 'stock.consumable', 'installer', 'photos'])
            );
        });
    }

    public function destroy(ConsumableInstallation $installation): JsonResponse
    {
        try {
            DB::transaction(function () use ($installation) {
                // Guardar datos antes de eliminar
                $stockId = $installation->stock_id;
                $quantity = $installation->quantity;
                $installationId = $installation->id;

                // Eliminar fotos
                foreach ($installation->photos as $photo) {
                    if (Storage::disk('public')->exists($photo->photo_path)) {
                        Storage::disk('public')->delete($photo->photo_path);
                    }
                    $photo->delete();
                }

                // Eliminar la instalación primero
                $installation->delete();

                // Restaurar stock después de eliminar la instalación
                if ($stockId) {
                    $stock = Stock::find($stockId);
                    
                    if ($stock) {
                        $stock->increment('quantity', $quantity);

                        // Registrar movimiento
                        $stock->movements()->create([
                            'movement_type' => 'in',
                            'quantity' => $quantity,
                            'note' => "Reversión de instalación #{$installationId}",
                            'performed_by' => auth()->id(),
                        ]);
                    }
                }
            });

            return response()->json(['message' => __('Instalación eliminada')]);
        } catch (\Exception $e) {
            \Log::error('Error deleting installation: ' . $e->getMessage());
            return response()->json([
                'message' => __('Error al eliminar instalación: :error', ['error' => $e->getMessage()])
            ], 500);
        }
    }
}

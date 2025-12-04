<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderComment;
use App\Models\OrderEntry;
use App\Models\OrderEntryItem;
use App\Models\Stock;
use App\Models\Consumable;
use App\Models\ConsumableReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\File;

class OrderEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = OrderEntry::with(['order', 'receiver', 'site', 'department', 'items.consumableReference']);

        if ($request->filled('order_id')) {
            $query->where('order_id', $request->integer('order_id'));
        }

        $perPage = $request->integer('per_page', 15);
        $entries = $query->latest('received_at')->paginate($perPage);

        // Asegurar que los items se serialicen correctamente con sus referencias
        $entries->getCollection()->transform(function ($entry) {
            $entry->load('items.consumableReference');
            return $entry;
        });

        return response()->json($entries);
    }

    public function store(Request $request): JsonResponse
    {
        // Convertir is_from_order de string a boolean si viene como string
        $isFromOrder = $request->input('is_from_order');
        if (is_string($isFromOrder)) {
            $isFromOrder = $isFromOrder === '1' || $isFromOrder === 'true';
        }

        // Parsear items si viene como JSON string
        $items = $request->input('items');
        if (is_string($items)) {
            $items = json_decode($items, true);
            $request->merge(['items' => $items]);
        }

        $validated = $request->validate([
            'is_from_order' => ['required', 'boolean'],
            'order_id' => ['required_if:is_from_order,true', 'nullable', 'exists:orders,id'],
            'site_id' => ['required_if:is_from_order,false', 'nullable', 'exists:sites,id'],
            'department_id' => ['sometimes', 'nullable', 'exists:departments,id'],
            'received_at' => ['required', 'date'],
            'delivery_note' => ['sometimes', 'nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(5120)],
            'notes' => ['sometimes', 'nullable', 'string'],
            'items' => ['required_if:is_from_order,false', 'array', 'min:1'],
            'items.*.consumable_reference_id' => ['required_with:items', 'exists:consumable_references,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
        ]);

        return DB::transaction(function () use ($request, $validated) {
            $deliveryNotePath = null;
            $deliveryNoteMimeType = null;

            if ($request->hasFile('delivery_note')) {
                $file = $request->file('delivery_note');
                $deliveryNotePath = $file->store('order-entries', 'public');
                $deliveryNoteMimeType = $file->getMimeType();
            }

            $entry = OrderEntry::create([
                'order_id' => $validated['is_from_order'] ? ($validated['order_id'] ?? null) : null,
                'site_id' => $validated['is_from_order'] ? null : ($validated['site_id'] ?? null),
                'department_id' => $validated['is_from_order'] ? null : ($validated['department_id'] ?? null),
                'received_at' => $validated['received_at'],
                'delivery_note_path' => $deliveryNotePath,
                'delivery_note_mime_type' => $deliveryNoteMimeType,
                'notes' => $validated['notes'] ?? null,
                'received_by' => auth()->id(),
            ]);

            // Si es por pedido, procesar items del pedido, actualizar stock y crear comentario
            if ($validated['is_from_order'] && $entry->order_id) {
                $order = Order::with(['items.consumableReference', 'printer.site'])->find($entry->order_id);
                
                if (!$order) {
                    throw new \Exception('Pedido no encontrado');
                }
                
                // Procesar cada item del pedido
                foreach ($order->items as $orderItem) {
                    // Crear item de entrada basado en el item del pedido
                    $entry->items()->create([
                        'consumable_reference_id' => $orderItem->consumable_reference_id,
                        'quantity' => $orderItem->quantity,
                    ]);

                    if ($orderItem->consumable_reference_id) {
                        $reference = ConsumableReference::find($orderItem->consumable_reference_id);
                        if ($reference) {
                            // Buscar o crear consumable basado en la referencia
                            $consumable = Consumable::firstOrCreate(
                                ['sku' => $reference->sku],
                                [
                                    'name' => $reference->name,
                                    'brand' => $reference->brand,
                                    'type' => $reference->type,
                                    'color' => $reference->color,
                                ]
                            );

                            // Obtener site_id y department_id del pedido o de la entrada
                            $siteId = $order->printer->site_id ?? $validated['site_id'] ?? null;
                            $departmentId = $validated['department_id'] ?? null;

                            // Actualizar o crear stock
                            $stock = Stock::firstOrCreate(
                                [
                                    'consumable_id' => $consumable->id,
                                    'site_id' => $siteId,
                                    'department_id' => $departmentId,
                                ],
                                [
                                    'quantity' => 0,
                                    'minimum_quantity' => $reference->minimum_quantity ?? 0
                                ]
                            );

                            $stock->increment('quantity', $orderItem->quantity);

                            // Registrar movimiento de stock
                            $stock->movements()->create([
                                'movement_type' => 'in',
                                'quantity' => $orderItem->quantity,
                                'note' => "Entrada registrada desde pedido #{$order->id}, entrada #{$entry->id}",
                                'reference_type' => OrderEntry::class,
                                'reference_id' => $entry->id,
                                'performed_by' => auth()->id(),
                                'movement_at' => $entry->received_at ?? now(),
                            ]);
                        }
                    }
                }
                
                // Actualizar estado del pedido
                $order->update([
                    'status' => 'received',
                    'received_at' => $entry->received_at,
                ]);
                
                // Crear comentario automático
                $order->comments()->create([
                    'comment' => "Pedido recibido el " . $entry->received_at->format('d/m/Y H:i') . ". Entrada registrada #{$entry->id}.",
                    'created_by' => auth()->id(),
                ]);
            }

            // Si no es por pedido, crear items y actualizar stock
            if (!$validated['is_from_order'] && isset($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $entry->items()->create([
                        'consumable_reference_id' => $itemData['consumable_reference_id'],
                        'quantity' => $itemData['quantity'],
                    ]);

                    // Buscar o crear consumable basado en la referencia
                    $reference = ConsumableReference::find($itemData['consumable_reference_id']);
                    if ($reference) {
                        $consumable = Consumable::firstOrCreate(
                            ['sku' => $reference->sku],
                            [
                                'name' => $reference->name,
                                'brand' => $reference->brand,
                                'type' => $reference->type,
                                'color' => $reference->color,
                            ]
                        );

                        // Actualizar o crear stock usando minimum_quantity de la referencia
                        $stock = Stock::firstOrCreate(
                            [
                                'consumable_id' => $consumable->id,
                                'site_id' => $validated['site_id'],
                                'department_id' => $validated['department_id'] ?? null,
                            ],
                            [
                                'quantity' => 0,
                                'minimum_quantity' => $reference->minimum_quantity ?? 0
                            ]
                        );

                        $stock->increment('quantity', $itemData['quantity']);

                        // Registrar movimiento
                        $stock->movements()->create([
                            'movement_type' => 'in',
                            'quantity' => $itemData['quantity'],
                            'note' => "Entrada registrada desde entrada #{$entry->id}",
                            'reference_type' => OrderEntry::class,
                            'reference_id' => $entry->id,
                            'performed_by' => auth()->id(),
                            'movement_at' => $entry->received_at ?? now(),
                        ]);
                    }
                }
            }

            return response()->json([
                'message' => __('Entrada registrada'),
                'data' => $entry->load(['order', 'receiver', 'items.consumableReference', 'site', 'department']),
                'delivery_note_url' => $deliveryNotePath ? Storage::disk('public')->url($deliveryNotePath) : null,
            ], 201);
        });
    }

    public function show(OrderEntry $orderEntry): JsonResponse
    {
        $orderEntry->load(['order', 'receiver', 'site.province', 'department', 'items.consumableReference']);

        return response()->json([
            'data' => $orderEntry,
            'delivery_note_url' => $orderEntry->delivery_note_path ? Storage::disk('public')->url($orderEntry->delivery_note_path) : null,
        ]);
    }

    public function update(Request $request, OrderEntry $orderEntry): JsonResponse
    {
        $validated = $request->validate([
            'received_at' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'delivery_note' => ['sometimes', 'nullable', File::types(['pdf', 'jpg', 'jpeg', 'png'])->max(5120)],
            'province_id' => ['sometimes', 'nullable', 'exists:provinces,id'],
            'site_id' => ['sometimes', 'nullable', 'exists:sites,id'],
            'department_id' => ['sometimes', 'nullable', 'exists:departments,id'],
        ]);

        if ($request->hasFile('delivery_note')) {
            // Eliminar el albarán anterior si existe
            if ($orderEntry->delivery_note_path) {
                Storage::disk('public')->delete($orderEntry->delivery_note_path);
            }

            $file = $request->file('delivery_note');
            $validated['delivery_note_path'] = $file->store('order-entries', 'public');
            $validated['delivery_note_mime_type'] = $file->getMimeType();
        }

        // Si se está actualizando la ubicación, actualizar site_id y department_id
        // FormData: verificar directamente con input() ya que has() puede fallar con FormData
        $siteId = $request->input('site_id');
        $deptId = $request->input('department_id');
        
        // Si los valores están presentes (incluso como string vacío), procesarlos
        if ($siteId !== null) {
            $validated['site_id'] = ($siteId === '' || $siteId === null) ? null : (int) $siteId;
        }
        if ($deptId !== null) {
            $validated['department_id'] = ($deptId === '' || $deptId === null) ? null : (int) $deptId;
        }

        Log::info('Updating OrderEntry', [
            'entry_id' => $orderEntry->id,
            'raw_site_id' => $siteId,
            'raw_department_id' => $deptId,
            'site_id_type' => gettype($siteId),
            'department_id_type' => gettype($deptId),
            'validated' => $validated,
        ]);

        $orderEntry->update($validated);
        
        Log::info('OrderEntry updated', [
            'entry_id' => $orderEntry->id,
            'site_id' => $orderEntry->site_id,
            'department_id' => $orderEntry->department_id,
        ]);

        return response()->json([
            'message' => __('Entrada actualizada'),
            'data' => $orderEntry->fresh(['order', 'receiver', 'site', 'department', 'items.consumableReference']),
            'delivery_note_url' => $orderEntry->delivery_note_path ? Storage::disk('public')->url($orderEntry->delivery_note_path) : null,
        ]);
    }

    public function destroy(OrderEntry $orderEntry): JsonResponse
    {
        // Eliminar el albarán si existe
        if ($orderEntry->delivery_note_path) {
            Storage::disk('public')->delete($orderEntry->delivery_note_path);
        }

        // Eliminar los items asociados
        $orderEntry->items()->delete();

        // Eliminar movimientos de stock relacionados
        DB::table('stock_movements')
            ->where('reference_type', OrderEntry::class)
            ->where('reference_id', $orderEntry->id)
            ->delete();

        // Revertir el stock (restar las cantidades)
        $orderEntry->load('items.consumableReference');
        foreach ($orderEntry->items as $item) {
            if ($item->consumableReference) {
                $consumable = Consumable::where('sku', $item->consumableReference->sku)->first();
                if ($consumable) {
                    $stock = Stock::where('consumable_id', $consumable->id)
                        ->where('site_id', $orderEntry->site_id)
                        ->where('department_id', $orderEntry->department_id)
                        ->first();
                    
                    if ($stock) {
                        $stock->decrement('quantity', $item->quantity);
                    }
                }
            }
        }

        // Si la entrada estaba asociada a un pedido, revertir el estado del pedido
        if ($orderEntry->order_id) {
            $order = Order::find($orderEntry->order_id);
            if ($order && $order->status === 'received') {
                $order->update([
                    'status' => 'in_progress',
                    'received_at' => null,
                ]);
            }
        }

        $orderEntry->delete();

        return response()->json(['message' => __('Entrada eliminada')]);
    }
}

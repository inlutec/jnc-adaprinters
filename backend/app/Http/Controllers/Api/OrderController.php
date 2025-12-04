<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendOrderEmail;
use App\Models\Order;
use App\Models\OrderComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['printer.site', 'consumable', 'creator', 'items' => function ($q) {
            $q->with('consumableReference');
        }]);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('printer_id')) {
            $query->where('printer_id', $request->integer('printer_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('requested_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('requested_at', '<=', $request->date('date_to'));
        }

        $perPage = $request->integer('per_page', 15);
        $orders = $query->latest('requested_at')->paginate($perPage);

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'printer_id' => ['sometimes', 'nullable', 'exists:printers,id'],
            'consumable_id' => ['sometimes', 'nullable', 'exists:consumables,id'],
            'supplier_name' => ['required', 'string', 'max:255'],
            'email_to' => ['required', 'email'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.consumable_reference_id' => ['required', 'exists:consumable_references,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.description' => ['sometimes', 'nullable', 'string'],
        ]);

        $order = Order::create([
            'printer_id' => $validated['printer_id'] ?? null,
            'consumable_id' => $validated['consumable_id'] ?? null,
            'status' => 'pending',
            'requested_at' => now(),
            'supplier_name' => $validated['supplier_name'],
            'email_to' => $validated['email_to'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        // Crear items del pedido
        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'consumable_reference_id' => $item['consumable_reference_id'],
                'quantity' => $item['quantity'],
                'description' => $item['description'] ?? null,
            ]);
        }

        // Encolar envío de email
        SendOrderEmail::dispatch($order);

        return response()->json($order->load(['printer', 'consumable', 'items.consumableReference']), 201);
    }

    public function show(Order $order): JsonResponse
    {
        $order->load(['printer.site', 'consumable', 'creator', 'items.consumable', 'items.consumableReference', 'entries.receiver', 'comments.creator']);

        return response()->json($order);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,in_progress,received,cancelled'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ]);

        // Si se marca como recibido, actualizar fecha
        if (isset($validated['status']) && $validated['status'] === 'received' && $order->status !== 'received') {
            $validated['received_at'] = now();
        }

        // Si se marca como en curso, actualizar fecha
        if (isset($validated['status']) && $validated['status'] === 'in_progress' && $order->status !== 'in_progress') {
            $validated['sent_at'] = now();
        }

        $order->update($validated);

        return response()->json([
            'message' => __('Pedido actualizado'),
            'data' => $order->fresh(['printer', 'consumable', 'comments.creator']),
        ]);
    }

    public function addComment(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        $comment = $order->comments()->create([
            'comment' => $validated['comment'],
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'message' => __('Comentario añadido'),
            'data' => $comment->load('creator'),
        ], 201);
    }

    public function getComments(Order $order): JsonResponse
    {
        $comments = $order->comments()->with('creator')->latest()->get();

        return response()->json($comments);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Alert::query()->with(['printer.site', 'site', 'department']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        $alerts = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json($alerts);
    }

    public function show(Alert $alert): JsonResponse
    {
        $alert->load(['printer.site', 'site', 'department', 'consumable']);

        return response()->json($alert);
    }

    public function update(Request $request, Alert $alert): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:open,acknowledged,resolved,dismissed'],
        ]);

        $alert->update($data);

        return response()->json($alert);
    }

    public function acknowledge(Request $request, Alert $alert): JsonResponse
    {
        $alert->update([
            'status' => 'acknowledged',
            'acknowledged_by' => $request->user()?->id,
            'acknowledged_at' => now(),
        ]);

        return response()->json($alert);
    }

    public function resolve(Alert $alert): JsonResponse
    {
        $alert->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return response()->json($alert);
    }

    public function dismiss(Alert $alert): JsonResponse
    {
        $alert->update([
            'status' => 'dismissed',
        ]);

        return response()->json($alert);
    }

    public function destroy(Alert $alert): JsonResponse
    {
        $alert->delete();

        return response()->json(['message' => __('Alerta eliminada')]);
    }
}


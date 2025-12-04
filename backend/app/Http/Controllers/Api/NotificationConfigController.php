<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationConfig;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $configs = NotificationConfig::all();

        return response()->json($configs);
    }

    public function show(NotificationConfig $notificationConfig): JsonResponse
    {
        return response()->json($notificationConfig);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:email,sms,webhook'],
            'name' => ['required', 'string', 'max:255'],
            'smtp_host' => ['required_if:type,email', 'nullable', 'string'],
            'smtp_port' => ['required_if:type,email', 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['sometimes', 'nullable', 'string'],
            'smtp_password' => ['sometimes', 'nullable', 'string'],
            'smtp_encryption' => ['sometimes', 'nullable', 'string', 'in:tls,ssl'],
            'from_address' => ['required_if:type,email', 'nullable', 'email'],
            'from_name' => ['sometimes', 'nullable', 'string'],
            'alert_thresholds' => ['sometimes', 'nullable', 'array'],
            'recipients' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $config = NotificationConfig::create($validated);

        return response()->json($config, 201);
    }

    public function update(Request $request, NotificationConfig $notificationConfig): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'smtp_host' => ['sometimes', 'nullable', 'string'],
            'smtp_port' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:65535'],
            'smtp_username' => ['sometimes', 'nullable', 'string'],
            'smtp_password' => ['sometimes', 'nullable', 'string'],
            'smtp_encryption' => ['sometimes', 'nullable', 'string', 'in:tls,ssl'],
            'from_address' => ['sometimes', 'nullable', 'email'],
            'from_name' => ['sometimes', 'nullable', 'string'],
            'alert_thresholds' => ['sometimes', 'nullable', 'array'],
            'recipients' => ['sometimes', 'nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $notificationConfig->update($validated);

        return response()->json([
            'message' => __('Configuración actualizada'),
            'data' => $notificationConfig->fresh(),
        ]);
    }

    public function test(NotificationConfig $notificationConfig, NotificationService $notificationService): JsonResponse
    {
        $result = $notificationService->testConnection($notificationConfig);

        return response()->json($result);
    }

    public function destroy(NotificationConfig $notificationConfig): JsonResponse
    {
        $notificationConfig->delete();

        return response()->json(['message' => __('Configuración eliminada')]);
    }
}

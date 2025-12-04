<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SnmpProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SnmpProfileController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(SnmpProfile::orderByDesc('is_default')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $profile = SnmpProfile::create($this->validatedData($request));

        return response()->json($profile, 201);
    }

    public function show(SnmpProfile $snmpProfile): JsonResponse
    {
        return response()->json($snmpProfile);
    }

    public function update(Request $request, SnmpProfile $snmpProfile): JsonResponse
    {
        $snmpProfile->update($this->validatedData($request, update: true));

        return response()->json($snmpProfile);
    }

    public function destroy(SnmpProfile $snmpProfile): JsonResponse
    {
        abort_if($snmpProfile->printers()->exists(), 422, __('Profile in use by printers'));

        $snmpProfile->delete();

        return response()->json([
            'message' => __('Profile deleted'),
        ]);
    }

    public function test(SnmpProfile $snmpProfile): JsonResponse
    {
        // Placeholder response until SNMP worker is wired.
        return response()->json([
            'message' => __('Test executed'),
            'profile' => $snmpProfile,
            'status' => 'pending_implementation',
        ]);
    }

    private function validatedData(Request $request, bool $update = false): array
    {
        return $request->validate([
            'name' => [$update ? 'sometimes' : 'required', 'string', 'max:255'],
            'version' => ['nullable', 'in:v1,v2c,v3'],
            'community' => ['nullable', 'string', 'max:255'],
            'security_level' => ['nullable', 'string', 'max:50'],
            'security_username' => ['nullable', 'string', 'max:255'],
            'auth_protocol' => ['nullable', 'string', 'max:50'],
            'auth_password' => ['nullable', 'string', 'max:255'],
            'priv_protocol' => ['nullable', 'string', 'max:50'],
            'priv_password' => ['nullable', 'string', 'max:255'],
            'context_name' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'timeout_ms' => ['nullable', 'integer', 'min:500'],
            'retries' => ['nullable', 'integer', 'min:0', 'max:5'],
            'is_default' => ['boolean'],
            'description' => ['nullable', 'string'],
            'oid_map' => ['nullable', 'array'],
        ]);
    }
}


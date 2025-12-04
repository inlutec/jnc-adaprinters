<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SnmpOid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SnmpOidController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SnmpOid::query();

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('is_system')) {
            $query->where('is_system', $request->boolean('is_system'));
        }

        $oids = $query->orderBy('category')->orderBy('name')->get();

        return response()->json($oids);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'oid' => ['required', 'string', 'unique:snmp_oids,oid'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['required', 'string', 'in:consumable,counter,status,environment,system'],
            'data_type' => ['sometimes', 'string', 'in:string,integer,gauge,counter'],
            'unit' => ['sometimes', 'nullable', 'string'],
            'color' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $validated['is_system'] = false; // Los OIDs creados manualmente no son del sistema

        $oid = SnmpOid::create($validated);

        return response()->json($oid, 201);
    }

    public function show(SnmpOid $snmpOid): JsonResponse
    {
        return response()->json($snmpOid);
    }

    public function update(Request $request, SnmpOid $snmpOid): JsonResponse
    {
        // No permitir editar OIDs del sistema
        if ($snmpOid->is_system) {
            return response()->json([
                'message' => __('No se pueden editar OIDs del sistema'),
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'category' => ['sometimes', 'string', 'in:consumable,counter,status,environment,system'],
            'data_type' => ['sometimes', 'string', 'in:string,integer,gauge,counter'],
            'unit' => ['sometimes', 'nullable', 'string'],
            'color' => ['sometimes', 'nullable', 'string'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ]);

        $snmpOid->update($validated);

        return response()->json([
            'message' => __('OID actualizado'),
            'data' => $snmpOid->fresh(),
        ]);
    }

    public function destroy(SnmpOid $snmpOid): JsonResponse
    {
        // No permitir eliminar OIDs del sistema
        if ($snmpOid->is_system) {
            return response()->json([
                'message' => __('No se pueden eliminar OIDs del sistema'),
            ], 403);
        }

        $snmpOid->delete();

        return response()->json(['message' => __('OID eliminado')]);
    }
}

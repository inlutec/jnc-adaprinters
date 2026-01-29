<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SnmpOid;
use App\Models\SnmpOidProfile;
use App\Services\Snmp\SnmpClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SnmpOidProfileController extends Controller
{
    public function index(): JsonResponse
    {
        $profiles = SnmpOidProfile::with('oids')
            ->orderByDesc('is_default')
            ->orderBy('brand')
            ->orderBy('model')
            ->get();

        return response()->json($profiles);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:snmp_oid_profiles,name'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'oid_ids' => ['nullable', 'array'],
            'oid_ids.*' => ['exists:snmp_oids,id'],
        ]);

        // Si se marca como default, desmarcar otros
        if ($validated['is_default'] ?? false) {
            SnmpOidProfile::where('is_default', true)->update(['is_default' => false]);
        }

        $profile = SnmpOidProfile::create([
            'name' => $validated['name'],
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Asociar OIDs si se proporcionaron
        if (isset($validated['oid_ids'])) {
            $syncData = [];
            foreach ($validated['oid_ids'] as $index => $oidId) {
                $syncData[$oidId] = [
                    'order' => $index,
                    'is_required' => false,
                ];
            }
            $profile->oids()->sync($syncData);
        }

        $profile->load('oids');

        return response()->json($profile, 201);
    }

    public function show(SnmpOidProfile $snmpOidProfile): JsonResponse
    {
        $snmpOidProfile->load('oids');
        return response()->json($snmpOidProfile);
    }

    public function update(Request $request, SnmpOidProfile $snmpOidProfile): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255', 'unique:snmp_oid_profiles,name,' . $snmpOidProfile->id],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'oid_ids' => ['nullable', 'array'],
            'oid_ids.*' => ['exists:snmp_oids,id'],
        ]);

        // Si se marca como default, desmarcar otros
        if (isset($validated['is_default']) && $validated['is_default']) {
            SnmpOidProfile::where('is_default', true)
                ->where('id', '!=', $snmpOidProfile->id)
                ->update(['is_default' => false]);
        }

        $snmpOidProfile->update([
            'name' => $validated['name'] ?? $snmpOidProfile->name,
            'brand' => $validated['brand'] ?? $snmpOidProfile->brand,
            'model' => $validated['model'] ?? $snmpOidProfile->model,
            'description' => $validated['description'] ?? $snmpOidProfile->description,
            'is_default' => $validated['is_default'] ?? $snmpOidProfile->is_default,
            'is_active' => $validated['is_active'] ?? $snmpOidProfile->is_active,
        ]);

        // Actualizar OIDs si se proporcionaron
        if (isset($validated['oid_ids'])) {
            $syncData = [];
            foreach ($validated['oid_ids'] as $index => $oidId) {
                $syncData[$oidId] = [
                    'order' => $index,
                    'is_required' => false,
                ];
            }
            $snmpOidProfile->oids()->sync($syncData);
        }

        $snmpOidProfile->load('oids');

        return response()->json($snmpOidProfile);
    }

    public function destroy(SnmpOidProfile $snmpOidProfile): JsonResponse
    {
        // Verificar si está en uso (podríamos añadir una relación con printers si es necesario)
        $snmpOidProfile->delete();

        return response()->json([
            'message' => __('Profile deleted'),
        ]);
    }

    /**
     * Añadir un OID al perfil
     */
    public function addOid(Request $request, SnmpOidProfile $snmpOidProfile): JsonResponse
    {
        $validated = $request->validate([
            'oid_id' => ['required', 'exists:snmp_oids,id'],
            'order' => ['nullable', 'integer', 'min:0'],
            'is_required' => ['boolean'],
        ]);

        $oid = SnmpOid::findOrFail($validated['oid_id']);
        $snmpOidProfile->addOid(
            $oid,
            $validated['order'] ?? 0,
            $validated['is_required'] ?? false
        );

        $snmpOidProfile->load('oids');

        return response()->json($snmpOidProfile);
    }

    /**
     * Remover un OID del perfil
     */
    public function removeOid(Request $request, SnmpOidProfile $snmpOidProfile): JsonResponse
    {
        $validated = $request->validate([
            'oid_id' => ['required', 'exists:snmp_oids,id'],
        ]);

        $oid = SnmpOid::findOrFail($validated['oid_id']);
        $snmpOidProfile->removeOid($oid);

        $snmpOidProfile->load('oids');

        return response()->json($snmpOidProfile);
    }

    /**
     * Obtener todos los OIDs disponibles (para seleccionar en el perfil)
     */
    public function availableOids(): JsonResponse
    {
        $oids = SnmpOid::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json($oids);
    }

    /**
     * Probar un OID contra una IP de impresora
     */
    public function testOid(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'oid' => ['required', 'string'],
            'community' => ['nullable', 'string', 'max:255'],
        ]);

        $snmpClient = app(\App\Services\Snmp\SnmpClient::class);
        $result = $snmpClient->testOid(
            $validated['ip'],
            $validated['oid'],
            $validated['community'] ?? null
        );

        return response()->json($result);
    }

    /**
     * Ejecutar SNMP walk para descubrir todos los OIDs de una impresora.
     * Pensado para el wizard "Crear perfil con SNMP walk".
     */
    public function snmpWalk(Request $request, SnmpClient $snmpClient): JsonResponse
    {
        $validated = $request->validate([
            'ip' => ['required', 'ip'],
            'community' => ['nullable', 'string', 'max:255'],
            'oid_base' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $snmpClient->walk(
            $validated['ip'],
            $validated['community'] ?? null,
            $validated['oid_base'] ?? null,
        );

        return response()->json($result);
    }

    /**
     * Crear un perfil OID a partir de la configuración resultante del SNMP walk.
     *
     * - Crea (o reutiliza) SnmpOid por OID string
     * - Crea el SnmpOidProfile
     * - Asocia los OIDs seleccionados al perfil en orden
     */
    public function createFromWalk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'profile' => ['required', 'array'],
            'profile.name' => ['required', 'string', 'max:255', 'unique:snmp_oid_profiles,name'],
            'profile.brand' => ['nullable', 'string', 'max:255'],
            'profile.model' => ['nullable', 'string', 'max:255'],
            'profile.description' => ['nullable', 'string'],
            'profile.is_default' => ['nullable', 'boolean'],
            'profile.is_active' => ['nullable', 'boolean'],

            'oids' => ['required', 'array', 'min:1'],
            'oids.*.oid' => ['required', 'string', 'max:255'],
            'oids.*.name' => ['required', 'string', 'max:255'],
            'oids.*.description' => ['nullable', 'string'],
            'oids.*.category' => ['required', 'string', 'in:consumable,counter,system,other'],
            'oids.*.data_type' => ['required', 'string', 'max:50'],
            'oids.*.unit' => ['nullable', 'string', 'max:50'],
            'oids.*.color' => ['nullable', 'string', 'in:black,cyan,magenta,yellow'],
            'oids.*.is_active' => ['nullable', 'boolean'],
            'oids.*.is_required' => ['nullable', 'boolean'],
            'oids.*.order' => ['nullable', 'integer', 'min:0'],
        ]);

        // Si se marca como default, desmarcar otros
        if (($validated['profile']['is_default'] ?? false) === true) {
            SnmpOidProfile::where('is_default', true)->update(['is_default' => false]);
        }

        $profile = SnmpOidProfile::create([
            'name' => $validated['profile']['name'],
            'brand' => $validated['profile']['brand'] ?? null,
            'model' => $validated['profile']['model'] ?? null,
            'description' => $validated['profile']['description'] ?? null,
            'is_default' => $validated['profile']['is_default'] ?? false,
            'is_active' => $validated['profile']['is_active'] ?? true,
        ]);

        $syncData = [];
        foreach ($validated['oids'] as $idx => $oidInput) {
            $oidModel = SnmpOid::firstOrCreate(
                ['oid' => $oidInput['oid']],
                [
                    'name' => $oidInput['name'],
                    'description' => $oidInput['description'] ?? null,
                    'category' => $oidInput['category'],
                    'data_type' => $oidInput['data_type'],
                    'unit' => $oidInput['unit'] ?? null,
                    'color' => $oidInput['color'] ?? null,
                    'is_system' => false,
                    'is_active' => $oidInput['is_active'] ?? true,
                    'metadata' => null,
                ]
            );

            // Si ya existía, actualizamos campos “editables” (sin pisar is_system)
            $oidModel->fill([
                'name' => $oidInput['name'],
                'description' => $oidInput['description'] ?? $oidModel->description,
                'category' => $oidInput['category'],
                'data_type' => $oidInput['data_type'],
                'unit' => $oidInput['unit'] ?? $oidModel->unit,
                'color' => $oidInput['color'] ?? $oidModel->color,
                'is_active' => $oidInput['is_active'] ?? $oidModel->is_active,
            ])->save();

            $order = $oidInput['order'] ?? $idx;
            $syncData[$oidModel->id] = [
                'order' => $order,
                'is_required' => (bool) ($oidInput['is_required'] ?? false),
            ];
        }

        $profile->oids()->sync($syncData);
        $profile->load('oids');

        return response()->json($profile, 201);
    }
}


<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Department::with('site.province');

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->integer('site_id'));
        }

        if ($request->filled('is_warehouse')) {
            $query->where('is_warehouse', $request->boolean('is_warehouse'));
        }

        $departments = $query->orderBy('name')->get();

        return response()->json($departments);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:25', 'unique:departments,code'],
            'floor' => ['sometimes', 'nullable', 'string'],
            'contact_email' => ['sometimes', 'nullable', 'email'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_warehouse' => ['sometimes', 'boolean'],
        ]);

        $department = Department::create($validated);

        return response()->json($department->load('site.province'), 201);
    }

    public function show(Department $department): JsonResponse
    {
        $department->load(['site.province']);

        return response()->json($department);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'site_id' => ['sometimes', 'exists:sites,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'nullable', 'string', 'max:25', 'unique:departments,code,' . $department->id],
            'floor' => ['sometimes', 'nullable', 'string'],
            'contact_email' => ['sometimes', 'nullable', 'email'],
            'contact_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_active' => ['sometimes', 'boolean'],
            'is_warehouse' => ['sometimes', 'boolean'],
        ]);

        $department->update($validated);

        return response()->json([
            'message' => __('Departamento actualizado'),
            'data' => $department->fresh(['site.province']),
        ]);
    }

    public function destroy(Department $department): JsonResponse
    {
        // Verificar que no tenga impresoras o stock asociado
        if ($department->printers()->count() > 0 || $department->stocks()->count() > 0) {
            return response()->json([
                'message' => __('No se puede eliminar un departamento con impresoras o stock asociado'),
            ], 422);
        }

        $department->delete();

        return response()->json(['message' => __('Departamento eliminado')]);
    }
}

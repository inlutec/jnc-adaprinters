<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomFieldController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = CustomField::query();

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->string('entity_type'));
        }

        $fields = $query->orderBy('entity_type')->orderBy('order')->get();

        return response()->json($fields);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'entity_type' => ['required', 'string', 'in:printer,consumable,order'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:text,number,date,select,checkbox,textarea'],
            'options' => ['required_if:type,select', 'array'],
            'is_required' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer'],
            'help_text' => ['sometimes', 'nullable', 'string'],
            'show_in_table' => ['sometimes', 'boolean'],
            'table_order' => ['sometimes', 'integer', 'min:0'],
            'show_in_creation_wizard' => ['sometimes', 'boolean'],
        ]);

        // Generar slug automáticamente
        $validated['slug'] = Str::slug($validated['name']);

        // Verificar unicidad del slug para esta entidad
        $exists = CustomField::where('entity_type', $validated['entity_type'])
            ->where('slug', $validated['slug'])
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => __('El campo con este nombre ya existe para esta entidad'),
            ], 422);
        }

        $field = CustomField::create($validated);

        return response()->json($field, 201);
    }

    public function show(CustomField $customField): JsonResponse
    {
        return response()->json($customField);
    }

    public function update(Request $request, CustomField $customField): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:text,number,date,select,checkbox,textarea'],
            'options' => ['sometimes', 'array'],
            'is_required' => ['sometimes', 'boolean'],
            'order' => ['sometimes', 'integer'],
            'help_text' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'show_in_table' => ['sometimes', 'boolean'],
            'table_order' => ['sometimes', 'integer', 'min:0'],
            'show_in_creation_wizard' => ['sometimes', 'boolean'],
        ]);

        $customField->update($validated);

        return response()->json([
            'message' => __('Campo personalizado actualizado'),
            'data' => $customField->fresh(),
        ]);
    }

    public function destroy(CustomField $customField): JsonResponse
    {
        // Eliminar también los valores asociados
        $customField->values()->delete();
        $customField->delete();

        return response()->json(['message' => __('Campo personalizado eliminado')]);
    }

    public function getFieldValues(string $slug): JsonResponse
    {
        $field = CustomField::where('slug', $slug)->first();

        if (!$field) {
            return response()->json(['message' => __('Campo personalizado no encontrado')], 404);
        }

        $values = $field->values()
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->distinct()
            ->pluck('value')
            ->sort()
            ->values()
            ->toArray();

        return response()->json(['values' => $values]);
    }
}

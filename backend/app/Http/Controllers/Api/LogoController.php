<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Logo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;

class LogoController extends Controller
{
    public function index(): JsonResponse
    {
        $logos = Logo::orderBy('type')->orderBy('created_at')->get();

        return response()->json($logos);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:web,email,header,footer'],
            'logo' => ['required', File::image()->max(2048)],
        ]);

        $file = $request->file('logo');
        $path = $file->store('logos', 'public');
        $mimeType = $file->getMimeType();
        $size = $file->getSize();

        // Obtener dimensiones de la imagen
        $imageInfo = getimagesize($file->getRealPath());
        $width = $imageInfo[0] ?? null;
        $height = $imageInfo[1] ?? null;

        // Desactivar otros logos del mismo tipo
        Logo::where('type', $validated['type'])->update(['is_active' => false]);

        $logo = Logo::create([
            'type' => $validated['type'],
            'path' => $path,
            'mime_type' => $mimeType,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'is_active' => true,
        ]);

        return response()->json([
            'message' => __('Logo subido correctamente'),
            'data' => $logo,
            'url' => Storage::disk('public')->url($path),
        ], 201);
    }

    public function show(Logo $logo): JsonResponse
    {
        return response()->json([
            'data' => $logo,
            'url' => Storage::disk('public')->url($logo->path),
        ]);
    }

    public function update(Request $request, Logo $logo): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $logo->update($validated);

        // Si se activa este logo, desactivar otros del mismo tipo
        if (isset($validated['is_active']) && $validated['is_active']) {
            Logo::where('type', $logo->type)
                ->where('id', '!=', $logo->id)
                ->update(['is_active' => false]);
        }

        return response()->json([
            'message' => __('Logo actualizado'),
            'data' => $logo->fresh(),
        ]);
    }

    public function destroy(Logo $logo): JsonResponse
    {
        Storage::disk('public')->delete($logo->path);
        $logo->delete();

        return response()->json(['message' => __('Logo eliminado')]);
    }
}

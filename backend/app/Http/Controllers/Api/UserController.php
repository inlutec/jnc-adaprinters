<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);
        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['sometimes', 'string', 'in:admin,manager,viewer'],
            'page_permissions' => ['sometimes', 'nullable', 'array'],
            'location_permissions' => ['sometimes', 'nullable', 'array'],
            'read_write_permissions' => ['sometimes', 'nullable', 'array'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'page_permissions' => $validated['page_permissions'] ?? null,
            'location_permissions' => $validated['location_permissions'] ?? null,
            'read_write_permissions' => $validated['read_write_permissions'] ?? null,
        ]);

        // TODO: Asignar roles cuando se instale Spatie Permission
        // if (isset($validated['role'])) {
        //     $user->assignRole($validated['role']);
        // }

        return response()->json($user, 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['sometimes', 'confirmed', Password::defaults()],
            'role' => ['sometimes', 'string', 'in:admin,manager,viewer'],
            'page_permissions' => ['sometimes', 'nullable', 'array'],
            'location_permissions' => ['sometimes', 'nullable', 'array'],
            'read_write_permissions' => ['sometimes', 'nullable', 'array'],
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        // TODO: Actualizar roles cuando se instale Spatie Permission
        // if (isset($validated['role'])) {
        //     $user->syncRoles([$validated['role']]);
        // }

        return response()->json([
            'message' => __('Usuario actualizado'),
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        // No permitir eliminar el usuario actual
        if ($user->id === auth()->id()) {
            return response()->json([
                'message' => __('No puedes eliminar tu propio usuario'),
            ], 403);
        }

        $user->delete();

        return response()->json(['message' => __('Usuario eliminado')]);
    }
}

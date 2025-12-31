<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate(20);

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        $user->load(['reservations', 'galleries', 'payments']);

        return response()->json([
            'user' => $user,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,client'],
        ]);

        $user = User::create([
            ...$validated,
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'user' => $user,
            'message' => 'Utilisateur créé avec succès.',
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:20'],
            'role' => ['sometimes', 'in:admin,client'],
        ]);

        $user->update($validated);

        return response()->json([
            'user' => $user->fresh(),
            'message' => 'Utilisateur mis à jour.',
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return response()->json([
                'message' => 'Impossible de supprimer le dernier administrateur.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'Utilisateur supprimé.',
        ]);
    }
}

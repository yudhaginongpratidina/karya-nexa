<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    private const DEFAULT_PASSWORD = '12345678';

    private function generateEmail(string $name): string
    {
        $base = Str::slug($name);
        $email = $base . '@gmail.com';
        $counter = 1;

        while (User::where('email', $email)->exists()) {
            $email = $base . $counter . '@gmail.com';
            $counter++;
        }

        return $email;
    }

    public function index(): JsonResponse
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'must_change_password', 'created_at'])
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
            'meta' => [
                'default_password' => self::DEFAULT_PASSWORD,
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::query()
            ->select(['id', 'name', 'email', 'role', 'must_change_password'])
            ->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'nullable|in:admin,user',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $this->generateEmail($validated['name']),
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'role' => $validated['role'] ?? 'user',
            'must_change_password' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User berhasil ditambahkan.',
            'data' => $user->only(['id', 'name', 'email', 'role', 'must_change_password']),
            'meta' => [
                'default_password' => self::DEFAULT_PASSWORD,
            ],
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'required|in:admin,user',
        ]);

        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        $user->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Data user berhasil diperbarui.',
            'data' => $user->fresh()->only(['id', 'name', 'email', 'role', 'must_change_password']),
        ]);
    }

    public function resetPassword(int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        $user->update([
            'password' => Hash::make(self::DEFAULT_PASSWORD),
            'must_change_password' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password user berhasil direset ke default.',
            'meta' => [
                'default_password' => self::DEFAULT_PASSWORD,
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak ditemukan.',
            ], 404);
        }

        if ($request->user()?->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Akun yang sedang digunakan tidak bisa dihapus.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dihapus.',
        ]);
    }
}

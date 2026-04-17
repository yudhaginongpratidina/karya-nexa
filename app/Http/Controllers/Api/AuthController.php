<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Models\User;

class AuthController extends Controller
{
    private function passwordRules(bool $requireCurrentPassword = false): array
    {
        return [
            'current_password' => [$requireCurrentPassword ? 'required' : 'nullable', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    private function generateUniqueEmail(string $name): string
    {
        $base = Str::slug($name);
        $email = $base . '@gmail.com';

        $count = User::where('email', 'LIKE', "$base%@gmail.com")->count();

        return $count > 0 ? "{$base}{$count}@gmail.com" : $email;
    }

    public function register(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'password' => 'required|string|min:8'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        }

        return DB::transaction(function () use ($request) {
            $email = $this->generateUniqueEmail($request->name);

            $user = User::create([
                'name' => $request->name,
                'email' => $email,
                'password' => Hash::make($request->password),
                'role' => 'user',
                'must_change_password' => false,
            ]);

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user,
                    'token' => $token
                ]
            ], 201);
        });
    }

    public function login(Request $request): JsonResponse
    {
        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string'
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        }

        $user = User::where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'token' => $user->createToken('auth_token')->plainTextToken,
                'must_change_password' => (bool) $user->must_change_password,
            ]
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out'
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()
        ]);
    }

    public function forceChangePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->must_change_password) {
            return response()->json([
                'success' => false,
                'message' => 'Password akun ini tidak sedang wajib diganti.',
            ], 422);
        }

        $validated = $request->validate($this->passwordRules(false));

        if (Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password baru harus berbeda dari password default.',
            ], 422);
        }

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui. Silakan lanjut ke dashboard.',
            'data' => [
                'user' => $user->fresh(),
            ],
        ]);
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate($this->passwordRules(true));

        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini tidak sesuai.',
            ], 422);
        }

        if (Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password baru harus berbeda dari password saat ini.',
            ], 422);
        }

        $user->update([
            'password' => $validated['password'],
            'must_change_password' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui.',
            'data' => [
                'user' => $user->fresh(),
            ],
        ]);
    }
}

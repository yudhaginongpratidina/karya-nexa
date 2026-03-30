<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    private function generateEmail(string $name): string
    {
        $base = strtolower(str_replace(' ', '-', $name));
        $email = $base . '@gmail.com';

        $counter = 1;

        while (User::where('email', $email)->exists()) {
            $email = $base . $counter . '@gmail.com';
            $counter++;
        }

        return $email;
    }

    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => User::latest()->get()
        ]);
    }

    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $email = $this->generateEmail($request->name);

        $user = User::create([
            'name' => $request->name,
            'email' => $email,
            'password' => Hash::make('12345678'),
            'role' => 'user',
        ]);

        return response()->json([
            'success' => true,
            'data' => $user
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'role' => 'sometimes|in:admin,user'
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        if ($request->name) {
            $user->name = $request->name;
            $user->email = $this->generateEmail($request->name);
        }

        if ($request->role) {
            $user->role = $request->role;
        }

        $user->save();

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted'
        ]);
    }
}

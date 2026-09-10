<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    // MENDAPATKAN PROFIL USER YANG SEDANG AKTIF (Endpoint /me)
    public function profile(Request $request)
    {
        return response()->json($request->user(), 200);
    }

    // GET ALL USERS (Khusus Admin untuk melihat semua pelanggan / user)
    public function index()
    {
        $users = User::latest()->get();
        return response()->json([
            'success' => true,
            'data'    => $users
        ], 200);
    }

    // CREATE USER (Admin bisa menambah user/admin baru)
    public function store(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users',
            'password'     => 'required|string|min:6',
            'phone_number' => 'nullable|string|max:20',
            'role'         => 'required|in:admin,customer',
        ]);

        $user = User::create([
            'name'         => $request->name,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'phone_number' => $request->phone_number,
            'role'         => $request->role,
        ]);

        return response()->json([
            'message' => 'User berhasil ditambahkan',
            'data'    => $user
        ], 201);
    }

    // GET SINGLE USER
    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        return response()->json(['data' => $user], 200);
    }

    // UPDATE USER (Admin mengedit data/role user)
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users,email,' . $id,
            'phone_number' => 'nullable|string|max:20',
            'role'         => 'required|in:admin,customer',
        ]);

        $dataToUpdate = [
            'name'         => $request->name,
            'email'        => $request->email,
            'phone_number' => $request->phone_number,
            'role'         => $request->role,
        ];

        // Jika password diisi, update password baru
        if ($request->filled('password')) {
            $dataToUpdate['password'] = Hash::make($request->password);
        }

        $user->update($dataToUpdate);

        return response()->json([
            'message' => 'Data user berhasil diperbarui',
            'data'    => $user
        ], 200);
    }

    // DELETE USER (Admin menghapus user)
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        $user->delete();

        return response()->json(['message' => 'User berhasil dihapus'], 200);
    }
}

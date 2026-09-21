<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

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
            'role'         => 'required|in:admin,customer,user',
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

    // UPDATE USER (Mengedit data profil & foto)
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        // 1. Validasi input (tanda koma di akhir baris photo sudah diperbaiki)
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|string|email|max:255|unique:users,email,' . $id,
            'phone_number' => 'nullable|string|max:20',
            'photo'        => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'role'         => 'sometimes|required|in:admin,customer,user',
            'password'     => 'nullable|string|min:6',
        ]);

        // 2. Masukkan data dasar yang akan di-update
        $dataToUpdate = [
            'name'         => $request->name,
            'email'        => $request->email,
            'phone_number' => $request->phone_number,
        ];

        // Jika field role dikirim dari frontend, ikut di-update
        if ($request->has('role')) {
            $dataToUpdate['role'] = $request->role;
        }

        // 3. Jika password diisi, hash password baru
        if ($request->filled('password')) {
            $dataToUpdate['password'] = Hash::make($request->password);
        }

        // 4. Tangani upload file foto baru jika ada
        if ($request->hasFile('photo')) {
            // Hapus foto lama di storage jika ada untuk menghemat ruang
            if ($user->photo && Storage::disk('public')->exists($user->photo)) {
                Storage::disk('public')->delete($user->photo);
            }

            // Simpan foto baru ke folder storage/app/public/avatars
            $path = $request->file('photo')->store('avatars', 'public');
            $dataToUpdate['photo'] = $path;
        }

        // 5. Simpan perubahan ke database
        $user->update($dataToUpdate);

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'user'    => $user
        ], 200);
    }

    // DELETE USER (Admin menghapus user)
    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User tidak ditemukan'], 404);
        }

        // Hapus file foto profil fisiknya jika ada
        if ($user->photo && Storage::disk('public')->exists($user->photo)) {
            Storage::disk('public')->delete($user->photo);
        }

        $user->delete();

        return response()->json(['message' => 'User berhasil dihapus'], 200);
    }
}

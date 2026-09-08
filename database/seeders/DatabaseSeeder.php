<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun Admin Uji
        User::create([
        'name' => 'Admin Test',
        'email' => 'admin@gmail.com',
        'password' => Hash::make('password123'),
        'phone_number' => '08123456789',
        'role' => 'admin',
        ]);

        // Akun User Uji
        User::create([
            'name' => 'Penyewa Uji',
            'phone_number' => '089876543210',
            'email' => 'user@camprent.com',
            'password' => Hash::make('password123'),
            'role' => 'user',
        ]);
    }
}

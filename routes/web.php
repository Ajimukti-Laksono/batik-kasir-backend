<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/fix-database', function () {
    try {
        // Force reset admin password using updateOrCreate
        $email = 'admin@batiknusantara.com';
        \App\Models\User::updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin Batik',
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
                'phone' => '081234567890',
            ]
        );
        
        // Run seeders for categories/products (now using updateOrCreate too)
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        
        return "Berhasil! User $email telah diset dengan password 'password123'. Silakan coba login kembali.";
    } catch (\Exception $e) {
        // If it's still failing, it might be due to the seeder or something else
        return 'Error saat memperbaiki database: ' . $e->getMessage();
    }
});

<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/fix-database', function () {
    try {
        // Reset Admin Password explicitly
        $email = 'admin@batiknusantara.com';
        $admin = \App\Models\User::where('email', $email)->first();
        
        if ($admin) {
            $admin->password = \Illuminate\Support\Facades\Hash::make('password123');
            $admin->save();
            $msg = "User $email found and password reset to 'password123'.";
        } else {
            \App\Models\User::create([
                'name' => 'Admin Batik',
                'email' => $email,
                'password' => \Illuminate\Support\Facades\Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
                'phone' => '081234567890',
            ]);
            $msg = "User $email not found, so it was created with password 'password123'.";
        }
        
        // Also run seeders for categories/products if they don't exist
        if (\App\Models\Category::count() == 0) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
            $msg .= " Also seeded other data.";
        }
        
        return $msg . " Silakan coba login kembali.";
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});

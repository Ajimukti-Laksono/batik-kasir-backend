<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/fix-database', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        return 'Database seeded successfully! You can login now.';
    } catch (\Exception $e) {
        // If error is duplicate entry, it means data exists. That's good too.
        return 'Database status: ' . $e->getMessage();
    }
});

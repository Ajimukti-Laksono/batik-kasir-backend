<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::any('/ping', function (Illuminate\Http\Request $request) {
    return response()->json([
        'status' => 'ok',
        'db_config' => config('database.connections.pgsql'),
        'database_url' => env('DATABASE_URL'),
        'postgres_url' => env('POSTGRES_URL')
    ]);
});
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/midtrans/callback', [TransactionController::class, 'midtransCallback']);

// InfinityFree setup route
Route::get('/setup-database-infinityfree', function () {
    try {
        // Neon pgBouncer pooler doesn't support migrations inside transactions properly.
        // We must override the DB connection to the non-pooled URL just for this command.
        $nonPooledUrl = str_replace('-pooler', '', env('DATABASE_URL'));
        config(['database.connections.pgsql.url' => $nonPooledUrl]);
        DB::purge('pgsql');

        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        return response()->json(['message' => 'Database successfully migrated and seeded for InfinityFree!']);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
});

// Storefront routes
Route::get('/storefront/products', [\App\Http\Controllers\Api\StorefrontController::class, 'products']);
Route::get('/storefront/categories', [\App\Http\Controllers\Api\StorefrontController::class, 'categories']);
Route::post('/storefront/checkout', [\App\Http\Controllers\Api\StorefrontController::class, 'checkout']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Categories
    Route::apiResource('categories', CategoryController::class);

    // Products
    Route::apiResource('products', ProductController::class);
    Route::post('/products/{product}/stock', [ProductController::class, 'updateStock']);
    Route::get('/products/search/barcode', [ProductController::class, 'searchByBarcode']);

    // Transactions
    Route::get('/transactions/report/summary', [TransactionController::class, 'report']);
    Route::get('/transactions/online/pending', [TransactionController::class, 'pendingOnline']);
    Route::put('/transactions/{transaction}/confirm', [TransactionController::class, 'confirmOnlineOrder']);
    Route::get('/transactions/{transaction}/sync', [TransactionController::class, 'syncStatus']);
    Route::apiResource('transactions', TransactionController::class)->only(['index', 'store', 'show']);

    // Users (Admin only)
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive']);
    });
});

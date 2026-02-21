<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Users
        User::updateOrCreate(
            ['email' => 'admin@batiknusantara.com'],
            [
                'name' => 'Admin Batik',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
                'phone' => '081234567890',
            ]
        );

        User::updateOrCreate(
            ['email' => 'manager@batiknusantara.com'],
            [
                'name' => 'Manager Toko',
                'password' => Hash::make('password123'),
                'role' => 'manager',
                'is_active' => true,
                'phone' => '081234567891',
            ]
        );

        User::updateOrCreate(
            ['email' => 'kasir1@batiknusantara.com'],
            [
                'name' => 'Kasir Satu',
                'password' => Hash::make('password123'),
                'role' => 'kasir',
                'is_active' => true,
                'phone' => '081234567892',
            ]
        );

        User::updateOrCreate(
            ['email' => 'kasir2@batiknusantara.com'],
            [
                'name' => 'Kasir Dua',
                'password' => Hash::make('password123'),
                'role' => 'kasir',
                'is_active' => true,
                'phone' => '081234567893',
            ]
        );

        // Categories
        $categories = [
            ['name' => 'Batik Formal', 'slug' => 'batik-formal', 'description' => 'Koleksi batik untuk acara formal'],
            ['name' => 'Batik Casual', 'slug' => 'batik-casual', 'description' => 'Batik santai untuk sehari-hari'],
            ['name' => 'Batik Modern', 'slug' => 'batik-modern', 'description' => 'Desain batik kontemporer'],
            ['name' => 'Batik Traditional', 'slug' => 'batik-traditional', 'description' => 'Batik klasik warisan budaya'],
            ['name' => 'Aksesoris Batik', 'slug' => 'aksesoris-batik', 'description' => 'Pelengkap gaya dengan motif batik'],
        ];

        foreach ($categories as $cat) {
            Category::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        // Products
        $products = [
            ['name' => 'Kemeja Batik Parang Modern', 'sku' => 'KBP-001', 'category_id' => 3, 'price' => 285000, 'cost_price' => 150000, 'stock' => 25, 'min_stock' => 5],
            ['name' => 'Dress Batik Mega Mendung', 'sku' => 'DBM-001', 'category_id' => 2, 'price' => 425000, 'cost_price' => 220000, 'stock' => 18, 'min_stock' => 5],
            ['name' => 'Blazer Batik Kombinasi', 'sku' => 'BBK-001', 'category_id' => 1, 'price' => 650000, 'cost_price' => 350000, 'stock' => 12, 'min_stock' => 3],
            ['name' => 'Sarung Batik Tulis Premium', 'sku' => 'SBT-001', 'category_id' => 4, 'price' => 550000, 'cost_price' => 280000, 'stock' => 8, 'min_stock' => 3],
            ['name' => 'Kemeja Batik Kawung', 'sku' => 'KBK-001', 'category_id' => 1, 'price' => 320000, 'cost_price' => 165000, 'stock' => 30, 'min_stock' => 5],
            ['name' => 'Rok Batik Plisket', 'sku' => 'RBP-001', 'category_id' => 2, 'price' => 275000, 'cost_price' => 140000, 'stock' => 20, 'min_stock' => 5],
            ['name' => 'Blouse Batik Kombinasi', 'sku' => 'BBK-002', 'category_id' => 3, 'price' => 295000, 'cost_price' => 150000, 'stock' => 22, 'min_stock' => 5],
            ['name' => 'Kain Batik Tulis Solo', 'sku' => 'KBT-001', 'category_id' => 4, 'price' => 850000, 'cost_price' => 450000, 'stock' => 5, 'min_stock' => 2],
            ['name' => 'Tas Batik Canvas', 'sku' => 'TBC-001', 'category_id' => 5, 'price' => 185000, 'cost_price' => 90000, 'stock' => 45, 'min_stock' => 10],
            ['name' => 'Celana Batik Kulot', 'sku' => 'CBK-001', 'category_id' => 2, 'price' => 265000, 'cost_price' => 135000, 'stock' => 28, 'min_stock' => 5],
            ['name' => 'Scarf Batik Sutera', 'sku' => 'SBS-001', 'category_id' => 5, 'price' => 245000, 'cost_price' => 120000, 'stock' => 15, 'min_stock' => 3],
            ['name' => 'Outer Batik Kimono', 'sku' => 'OBK-001', 'category_id' => 3, 'price' => 385000, 'cost_price' => 200000, 'stock' => 16, 'min_stock' => 5],
        ];

        foreach ($products as $prod) {
            Product::updateOrCreate(['sku' => $prod['sku']], $prod);
        }
    }
}

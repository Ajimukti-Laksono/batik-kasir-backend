<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        // Today stats
        $todayRevenue = Transaction::whereDate('created_at', today())
            ->where('payment_status', 'success')->sum('total');

        $todayTransactions = Transaction::whereDate('created_at', today())
            ->where('payment_status', 'success')->count();

        $totalProducts = Product::active()->count();

        $lowStockCount = Product::lowStock()->count();

        // Weekly revenue (last 7 days)
        $weeklyRevenue = Transaction::where('payment_status', 'success')
            ->where('created_at', '>=', now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, SUM(total) as revenue, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top products (this month)
        $topProducts = TransactionItem::whereHas('transaction', function($q) {
            $q->whereMonth('created_at', now()->month)
              ->where('payment_status', 'success');
        })
        ->selectRaw('product_name, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
        ->groupBy('product_name')
        ->orderByDesc('total_revenue')
        ->limit(5)
        ->get();

        // Low stock products
        $lowStockProducts = Product::lowStock()->active()
            ->orderBy('stock')->limit(5)->get();

        // Recent transactions
        $recentTransactions = Transaction::with('kasir:id,name')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'today_revenue' => $todayRevenue,
                'today_transactions' => $todayTransactions,
                'total_products' => $totalProducts,
                'low_stock_count' => $lowStockCount,
                'weekly_revenue' => $weeklyRevenue,
                'top_products' => $topProducts,
                'low_stock_products' => $lowStockProducts,
                'recent_transactions' => $recentTransactions,
            ]
        ]);
    }
}

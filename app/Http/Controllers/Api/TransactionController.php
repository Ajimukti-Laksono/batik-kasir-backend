<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Product;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    private MidtransService $midtrans;

    public function __construct(MidtransService $midtrans)
    {
        $this->midtrans = $midtrans;
    }

    public function index(Request $request)
    {
        $query = Transaction::with(['kasir', 'items'])
            ->orderBy('created_at', 'desc');

        if ($request->date_from) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->date_to) $query->whereDate('created_at', '<=', $request->date_to);
        if ($request->status) $query->where('payment_status', $request->status);
        if ($request->kasir_id) $query->where('kasir_id', $request->kasir_id);
        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('invoice_number', 'like', "%{$request->search}%")
                  ->orWhere('customer_name', 'like', "%{$request->search}%");
            });
        }

        $transactions = $query->paginate($request->per_page ?? 15);

        return response()->json(['success' => true, 'data' => $transactions]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.discount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'payment_method' => 'required|in:midtrans,cash,transfer',
            'notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Calculate totals
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stok {$product->name} tidak cukup. Stok tersedia: {$product->stock}"
                    ], 422);
                }

                $itemDiscount = $item['discount'] ?? 0;
                $itemSubtotal = ($product->price * $item['quantity']) - $itemDiscount;
                $subtotal += $itemSubtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'price' => $product->price,
                    'quantity' => $item['quantity'],
                    'discount' => $itemDiscount,
                    'subtotal' => $itemSubtotal,
                ];

                // Reduce stock
                $product->decrement('stock', $item['quantity']);
            }

            $discount = $validated['discount'] ?? 0;
            $taxPercentage = $validated['tax_percentage'] ?? 11;
            $taxableAmount = $subtotal - $discount;
            $tax = round($taxableAmount * ($taxPercentage / 100));
            $total = $taxableAmount + $tax;
            $midtransOrderId = 'BN-' . time() . '-' . rand(100, 999);

            $transaction = Transaction::create([
                'invoice_number' => Transaction::generateInvoice(),
                'kasir_id' => $request->user()->id,
                'customer_name' => $validated['customer_name'] ?? 'Umum',
                'customer_phone' => $validated['customer_phone'] ?? null,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'payment_status' => $validated['payment_method'] === 'cash' ? 'success' : 'pending',
                'midtrans_order_id' => null, // Placeholder, updated below
                'notes' => $validated['notes'] ?? null,
                'paid_at' => $validated['payment_method'] === 'cash' ? now() : null,
            ]);

            $transaction->update([
                'midtrans_order_id' => $transaction->invoice_number
            ]);

            foreach ($itemsData as $item) {
                $transaction->items()->create($item);
            }

            DB::commit();

            // Create Midtrans token if payment via midtrans
            if ($validated['payment_method'] === 'midtrans') {
                $transaction->load('items');
                $midtransResult = $this->midtrans->createTransaction($transaction);

                if ($midtransResult['success']) {
                    $transaction->update([
                        'midtrans_token' => $midtransResult['token'],
                        'midtrans_redirect_url' => $midtransResult['redirect_url'],
                    ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Transaksi berhasil dibuat',
                        'data' => [
                            'transaction' => $transaction->load(['kasir', 'items']),
                            'midtrans_token' => $midtransResult['token'],
                            'midtrans_redirect_url' => $midtransResult['redirect_url'],
                            'client_key' => $this->midtrans->getClientKey(),
                            'is_production' => $this->midtrans->isProduction(),
                        ]
                    ], 201);
                }

                // Rollback if midtrans fails
                DB::rollBack();
                return response()->json(['success' => false, 'message' => $midtransResult['message']], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibuat',
                'data' => $transaction->load(['kasir', 'items'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Transaction Error', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function show(Transaction $transaction)
    {
        return response()->json([
            'success' => true,
            'data' => $transaction->load(['kasir', 'items.product'])
        ]);
    }

    public function syncStatus(Transaction $transaction)
    {
        if ($transaction->payment_method !== 'midtrans' || $transaction->payment_status === 'success') {
            return response()->json(['success' => true, 'message' => 'Tidak perlu sinkronisasi', 'data' => $transaction]);
        }

        // Log the search attempt
        Log::info('Syncing status for Order ID: ' . $transaction->midtrans_order_id);

        $result = $this->midtrans->getStatus($transaction->midtrans_order_id);

        if ($result['success'] && isset($result['data']['transaction_status'])) {
            $midtransStatus = $result['data']['transaction_status'];
            $fraudStatus = $result['data']['fraud_status'] ?? '';
            
            Log::info('Midtrans Response for ' . $transaction->midtrans_order_id, ['status' => $midtransStatus]);

            $status = 'pending';
            if ($midtransStatus === 'capture') {
                $status = $fraudStatus === 'challenge' ? 'pending' : 'success';
            } elseif ($midtransStatus === 'settlement') {
                $status = 'success';
            } elseif (in_array($midtransStatus, ['cancel', 'deny', 'expire'])) {
                $status = 'failed';
            }

            if ($status !== $transaction->payment_status) {
                $transaction->update([
                    'payment_status' => $status,
                    'paid_at' => $status === 'success' ? now() : null,
                ]);
            }

            return response()->json(['success' => true, 'message' => 'Status disinkronisasi: ' . $status, 'data' => $transaction]);
        }

        Log::error('Midtrans Sync Error for ' . $transaction->midtrans_order_id, ['result' => $result]);
        return response()->json([
            'success' => false, 
            'message' => 'Gagal sinkronisasi atau transaksi tidak ditemukan di Midtrans'
        ], 400);
    }

    public function midtransCallback(Request $request)
    {
        $orderId = $request->order_id;
        $statusCode = $request->status_code;
        $grossAmount = $request->gross_amount;
        $signatureKey = $request->signature_key;
        $transactionStatus = $request->transaction_status;
        $fraudStatus = $request->fraud_status;

        // Verify signature
        if (!$this->midtrans->verifySignature($orderId, $statusCode, $grossAmount, $signatureKey)) {
            return response()->json(['success' => false, 'message' => 'Invalid signature'], 403);
        }

        $transaction = Transaction::where('midtrans_order_id', $orderId)->first();
        if (!$transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
        }

        $status = 'pending';
        if ($transactionStatus === 'capture') {
            $status = $fraudStatus === 'challenge' ? 'pending' : 'success';
        } elseif ($transactionStatus === 'settlement') {
            $status = 'success';
        } elseif (in_array($transactionStatus, ['cancel', 'deny', 'expire'])) {
            $status = 'failed';
            // Restore stock
            foreach ($transaction->items as $item) {
                if ($item->product) {
                    $item->product->increment('stock', $item->quantity);
                }
            }
        } elseif ($transactionStatus === 'refund') {
            $status = 'refunded';
        }

        $transaction->update([
            'payment_status' => $status,
            'paid_at' => $status === 'success' ? now() : null,
        ]);

        return response()->json(['success' => true]);
    }

    public function report(Request $request)
    {
        $dateFrom = $request->date_from ?? now()->startOfMonth()->toDateString();
        $dateTo = $request->date_to ?? now()->toDateString();

        $transactions = Transaction::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('payment_status', 'success')
            ->with(['items', 'kasir'])
            ->get();

        $totalRevenue = $transactions->sum('total');
        $totalTransactions = $transactions->count();
        $avgTransaction = $totalTransactions > 0 ? $totalRevenue / $totalTransactions : 0;

        // Daily breakdown
        $dailyReport = Transaction::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('payment_status', 'success')
            ->selectRaw('strftime("%Y-%m-%d", created_at) as date, COUNT(*) as count, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top products
        $topProducts = TransactionItem::whereHas('transaction', function($q) use ($dateFrom, $dateTo) {
            $q->whereDate('created_at', '>=', $dateFrom)
              ->whereDate('created_at', '<=', $dateTo)
              ->where('payment_status', 'success');
        })
        ->selectRaw('product_name, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
        ->groupBy('product_name')
        ->orderByDesc('total_revenue')
        ->limit(10)
        ->get();

        // Kasir performance
        $kasirReport = Transaction::whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->where('payment_status', 'success')
            ->with('kasir:id,name')
            ->selectRaw('kasir_id, COUNT(*) as count, SUM(total) as revenue')
            ->groupBy('kasir_id')
            ->orderByDesc('revenue')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_revenue' => $totalRevenue,
                    'total_transactions' => $totalTransactions,
                    'avg_transaction' => $avgTransaction,
                ],
                'daily_report' => $dailyReport,
                'top_products' => $topProducts,
                'kasir_report' => $kasirReport,
            ]
        ]);
    }
}

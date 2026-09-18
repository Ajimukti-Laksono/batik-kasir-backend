<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StorefrontController extends Controller
{
    public function products(Request $request)
    {
        $query = Product::with('category')->active();

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->orderBy('name')->get();

        return response()->json(['success' => true, 'data' => $products]);
    }

    public function categories(Request $request)
    {
        $categories = Category::orderBy('name')->get();
        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function checkout(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Stok {$product->name} tidak cukup. Tersedia: {$product->stock}"
                    ], 422);
                }

                $itemSubtotal = $product->price * $item['quantity'];
                $subtotal += $itemSubtotal;

                $itemsData[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'product_sku' => $product->sku,
                    'price' => $product->price,
                    'quantity' => $item['quantity'],
                    'discount' => 0,
                    'subtotal' => $itemSubtotal,
                ];

                // Reduce stock
                $product->decrement('stock', $item['quantity']);
            }

            // We default to a tax of 0 for online orders for simplicity
            $tax = 0; 
            $total = $subtotal + $tax;

            // Optional: assign a specific user ID for online orders
            // For now, let's pick the first admin as the processor.
            $systemUser = \App\Models\User::where('role', 'admin')->first();

            $transaction = Transaction::create([
                'invoice_number' => Transaction::generateInvoice(),
                'kasir_id' => $systemUser ? $systemUser->id : 1, // Fallback to 1
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'] ?? null,
                'subtotal' => $subtotal,
                'discount' => 0,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'pending', // Usually pending for online
                'midtrans_order_id' => null,
                'notes' => 'Online Store Order',
                'paid_at' => null,
            ]);

            $transaction->update([
                'midtrans_order_id' => $transaction->invoice_number
            ]);

            foreach ($itemsData as $item) {
                $transaction->items()->create($item);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat!',
                'data' => $transaction->load(['items'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Storefront Checkout Error', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Terjadi kesalahan sistem: ' . $e->getMessage()], 500);
        }
    }
}

<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MidtransService
{
    private string $serverKey;
    private string $clientKey;
    private bool $isProduction;
    private string $baseUrl;
    private string $apiBaseUrl;

    public function __construct()
    {
        $this->serverKey = config('midtrans.server_key');
        $this->clientKey = config('midtrans.client_key');
        // Robust boolean parsing
        $isProduction = config('midtrans.is_production');
        $this->isProduction = filter_var($isProduction, FILTER_VALIDATE_BOOLEAN);

        $this->baseUrl = $this->isProduction
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
        $this->apiBaseUrl = $this->isProduction
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    public function createTransaction(Transaction $transaction): array
    {
        $items = [];
        foreach ($transaction->items as $item) {
            $items[] = [
                'id' => (string)$item->product_id,
                'price' => (int)$item->price,
                'quantity' => $item->quantity,
                'name' => substr($item->product_name, 0, 50),
            ];
        }

        // Add tax as item if any
        if ($transaction->tax > 0) {
            $items[] = [
                'id' => 'TAX',
                'price' => (int)$transaction->tax,
                'quantity' => 1,
                'name' => 'Pajak (11%)',
            ];
        }

        // Subtract discount
        if ($transaction->discount > 0) {
            $items[] = [
                'id' => 'DISCOUNT',
                'price' => -(int)$transaction->discount,
                'quantity' => 1,
                'name' => 'Diskon',
            ];
        }

        $params = [
            'transaction_details' => [
                'order_id' => $transaction->midtrans_order_id,
                'gross_amount' => (int)$transaction->total,
            ],
            'item_details' => $items,
            'customer_details' => [
                'first_name' => $transaction->customer_name ?? 'Customer',
                'phone' => $transaction->customer_phone ?? '',
            ],
            'callbacks' => [
                'finish' => config('app.frontend_url') . '/pos/success/' . $transaction->id,
                'error' => config('app.frontend_url') . '/pos/failed/' . $transaction->id,
                'pending' => config('app.frontend_url') . '/pos/pending/' . $transaction->id,
            ],
            'expiry' => [
                'unit' => 'minute',
                'duration' => 60,
            ],
            'enabled_payments' => [
                'credit_card', 'bca_va', 'bni_va', 'bri_va',
                'mandiri_va', 'permata_va', 'gopay', 'shopeepay',
                'dana', 'ovo', 'qris',
            ],
        ];

        if (empty($this->serverKey)) {
            Log::error('Midtrans Server Key is missing');
            return ['success' => false, 'message' => 'Layanan Midtrans belum dikonfigurasi. Mohon atur Server Key di file .env'];
        }

        try {
            Log::info('Midtrans Attempt', [
                'server_key_length' => strlen($this->serverKey),
                'order_id' => $transaction->midtrans_order_id
            ]);

            $response = Http::withBasicAuth($this->serverKey, '')
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($this->baseUrl . '/transactions', $params);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'token' => $data['token'],
                    'redirect_url' => $data['redirect_url'],
                ];
            }

            $errorBody = $response->body();
            $errorData = $response->json();
            Log::error('Midtrans API Error', [
                'status' => $response->status(),
                'body' => $errorBody,
                'order_id' => $transaction->midtrans_order_id,
                'key_length' => strlen($this->serverKey)
            ]);
            
            $errorMessage = 'Gagal membuat transaksi Midtrans';
            if (isset($errorData['error_messages'][0])) {
                $errorMessage .= ': ' . $errorData['error_messages'][0];
            } elseif ($response->status() === 401) {
                $errorMessage .= ': Akses ditolak (Unauthorized). Pastikan Server Key di .env sudah benar dan sesuai dengan environment Sandbox.';
            }
            
            return ['success' => false, 'message' => $errorMessage];

        } catch (\Exception $e) {
            Log::error('Midtrans Exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Kesalahan koneksi Midtrans: ' . $e->getMessage()];
        }
    }

    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);
        return hash_equals($expected, $signatureKey);
    }

    public function getClientKey(): string
    {
        return $this->clientKey;
    }

    public function isProduction(): bool
    {
        return $this->isProduction;
    }

    public function getStatus(string $orderId): array
    {
        if (empty($this->serverKey)) return ['success' => false];

        try {
            $response = Http::withBasicAuth($this->serverKey, '')
                ->get($this->apiBaseUrl . '/' . $orderId . '/status');
            
            if ($response->successful()) {
                return ['success' => true, 'data' => $response->json()];
            }
            return ['success' => false, 'message' => $response->json()['status_message'] ?? 'Failed'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}

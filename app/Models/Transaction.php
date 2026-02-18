<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number', 'kasir_id', 'customer_name', 'customer_phone',
        'subtotal', 'discount', 'tax', 'total', 'payment_method',
        'payment_status', 'midtrans_order_id', 'midtrans_token',
        'midtrans_redirect_url', 'paid_at', 'notes'
    ];

    protected $casts = [
        'subtotal' => 'decimal:0',
        'discount' => 'decimal:0',
        'tax' => 'decimal:0',
        'total' => 'decimal:0',
        'paid_at' => 'datetime',
    ];

    // Payment statuses
    const STATUS_PENDING = 'pending';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_REFUNDED = 'refunded';

    public function kasir()
    {
        return $this->belongsTo(User::class, 'kasir_id');
    }

    public function items()
    {
        return $this->hasMany(TransactionItem::class);
    }

    public static function generateInvoice(): string
    {
        $prefix = 'BN-' . date('Ymd');
        $last = static::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')->first();
        $number = $last ? (int)substr($last->invoice_number, -4) + 1 : 1;
        return $prefix . '-' . str_pad($number, 4, '0', STR_PAD_LEFT);
    }
}

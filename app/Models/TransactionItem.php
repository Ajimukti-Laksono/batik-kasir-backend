<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionItem extends Model
{
    protected $fillable = [
        'transaction_id', 'product_id', 'product_name',
        'product_sku', 'price', 'quantity', 'discount', 'subtotal'
    ];

    protected $casts = [
        'price' => 'decimal:0',
        'discount' => 'decimal:0',
        'subtotal' => 'decimal:0',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}

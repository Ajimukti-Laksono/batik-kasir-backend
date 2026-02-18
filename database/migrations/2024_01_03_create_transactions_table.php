<?php
// database/migrations/2024_01_03_create_transactions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('kasir_id')->constrained('users')->onDelete('restrict');
            $table->string('customer_name')->default('Umum');
            $table->string('customer_phone')->nullable();
            $table->decimal('subtotal', 12, 0);
            $table->decimal('discount', 12, 0)->default(0);
            $table->decimal('tax', 12, 0)->default(0);
            $table->decimal('total', 12, 0);
            $table->enum('payment_method', ['midtrans', 'cash', 'transfer'])->default('cash');
            $table->enum('payment_status', ['pending', 'success', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->string('midtrans_order_id')->nullable();
            $table->string('midtrans_token')->nullable();
            $table->text('midtrans_redirect_url')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');
            $table->string('product_name');
            $table->string('product_sku');
            $table->decimal('price', 12, 0);
            $table->integer('quantity');
            $table->decimal('discount', 12, 0)->default(0);
            $table->decimal('subtotal', 12, 0);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // POS Shifts
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_cash', 15, 2)->default(0);
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->decimal('expected_cash', 15, 2)->nullable();
            $table->enum('status', ['open', 'closed', 'suspended'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['warehouse_id', 'status']);
        });

        // Sales Transactions (POS)
        Schema::create('sales_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number')->unique();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pos_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->enum('payment_method', ['cash', 'transfer', 'card', 'split', 'other'])->default('cash');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0);
            $table->enum('status', ['completed', 'voided', 'refunded'])->default('completed');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('warehouse_id');
            $table->index('transaction_number');
            $table->index('created_at');
        });

        Schema::create('sales_transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        // Sales Orders (B2B / non-POS)
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->id();
            $table->string('so_number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['draft', 'confirmed', 'processing', 'shipped', 'delivered', 'completed', 'cancelled'])->default('draft');
            $table->date('order_date');
            $table->date('delivery_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('customer_id');
            $table->index('status');
            $table->index('order_date');
        });

        Schema::create('sales_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        // Sales Returns
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->morphs('returnable'); // sales_transaction or sales_order
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->date('return_date');
            $table->enum('status', ['draft', 'approved', 'processed', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->boolean('restock')->default(true);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('sales_order_items');
        Schema::dropIfExists('sales_orders');
        Schema::dropIfExists('sales_transaction_items');
        Schema::dropIfExists('sales_transactions');
        Schema::dropIfExists('pos_shifts');
    }
};

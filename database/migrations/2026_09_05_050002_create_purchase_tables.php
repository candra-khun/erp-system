<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purchase Orders
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['draft', 'submitted', 'approved', 'sent_to_supplier', 'partial_received', 'received', 'completed', 'cancelled'])->default('draft');
            $table->date('order_date');
            $table->date('expected_delivery_date')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('supplier_id');
            $table->index('status');
            $table->index('order_date');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->decimal('received_quantity', 15, 2)->default(0);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });

        // Goods Receipt Notes (GRN)
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('grn_number')->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->date('receipt_date');
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();
        });

        // Purchase Returns
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_number')->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->date('return_date');
            $table->enum('status', ['draft', 'approved', 'processed', 'cancelled'])->default('draft');
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
    }
};

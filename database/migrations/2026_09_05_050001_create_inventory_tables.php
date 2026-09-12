<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Current stock per product per warehouse
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'warehouse_id']);
        });

        // Stock movement log (every in/out recorded here)
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer_in', 'transfer_out']);
            $table->decimal('quantity', 15, 2);
            $table->string('reference_type')->nullable(); // e.g., 'purchase_order', 'sales_transaction'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('movement_date')->useCurrent();
            $table->timestamps();

            $table->index(['product_id', 'warehouse_id']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('movement_date');
        });

        // Stock transfers between warehouses
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_number')->unique();
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'in_transit', 'received', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 2);
            $table->decimal('received_quantity', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stocks');
    }
};

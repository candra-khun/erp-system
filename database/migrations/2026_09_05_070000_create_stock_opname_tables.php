<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stock Opname Sessions
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_number', 50)->unique();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['draft', 'in_progress', 'completed', 'approved'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Stock Opname Items
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('system_quantity', 15, 2);
            $table->decimal('physical_quantity', 15, 2);
            $table->decimal('difference', 15, 2)->storedAs('physical_quantity - system_quantity');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Add reorder_point to products table
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('reorder_point', 15, 2)->nullable()->after('is_active');
            $table->boolean('reorder_alert_enabled')->default(true)->after('reorder_point');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['reorder_point', 'reorder_alert_enabled']);
        });

        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
    }
};

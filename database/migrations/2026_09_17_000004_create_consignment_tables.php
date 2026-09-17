<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Consignment management module (PRD Fase 4 #15).
     *
     * Barang titipan supplier: consignment_ins (penerimaan) →
     * consignment_items (detail stok titipan) → consignment_settlements
     * (perhitungan & pembayaran yang terjual → AP).
     */
    public function up(): void
    {
        // Penerimaan barang konsinyasi dari supplier
        Schema::create('consignment_ins', function (Blueprint $table) {
            $table->id();
            $table->string('consignment_number', 60)->unique(); // CG-...
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->date('received_date');
            $table->date('expiry_date')->nullable(); // batas waktu titipan
            $table->enum('status', ['open', 'partially_settled', 'settled', 'expired', 'cancelled'])->default('open');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
            $table->index('warehouse_id');
        });

        // Detail barang konsinyasi
        Schema::create('consignment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_in_id')->constrained('consignment_ins')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_received', 15, 2)->default(0);
            $table->decimal('quantity_sold', 15, 2)->default(0);
            $table->decimal('quantity_returned', 15, 2)->default(0);
            $table->decimal('quantity_available', 15, 2)->default(0); // diterima - terjual - dikembalikan
            $table->decimal('consignment_price', 15, 2)->default(0); // harga titip (dibayar ke supplier)
            $table->decimal('selling_price', 15, 2)->default(0); // harga jual
            $table->enum('status', ['active', 'sold_out', 'returned', 'settled'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status']);
        });

        // Settlement: perhitungan barang terjual untuk dibayar ke supplier
        Schema::create('consignment_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number', 60)->unique(); // CS-...
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_quantity_sold', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0); // yang harus dibayar ke supplier
            $table->decimal('commission_amount', 15, 2)->default(0);
            $table->foreignId('account_payable_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'confirmed', 'paid', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
        });

        // Detail item settlement
        Schema::create('consignment_settlement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consignment_settlement_id')->constrained('consignment_settlements')->cascadeOnDelete();
            $table->foreignId('consignment_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity_sold', 15, 2)->default(0);
            $table->decimal('consignment_price', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consignment_settlement_items');
        Schema::dropIfExists('consignment_settlements');
        Schema::dropIfExists('consignment_items');
        Schema::dropIfExists('consignment_ins');
    }
};

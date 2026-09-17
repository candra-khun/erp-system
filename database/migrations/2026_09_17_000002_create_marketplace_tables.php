<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketplace integration module (PRD Fase 4 #13).
     *
     * Channel (Tokopedia/Shopee/Tokotalk dll) → order sync → stok sync.
     * Sync log menyimpan payload mentah untuk audit.
     */
    public function up(): void
    {
        // Channel marketplace (multi-channel, per gudang)
        Schema::create('marketplace_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Tokopedia, Shopee, dll
            $table->enum('platform', ['tokopedia', 'shopee', 'lazada', 'bukalapak', 'tokotalk', 'other']);
            $table->string('shop_name')->nullable();
            $table->string('shop_id')->nullable();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->text('api_credential')->nullable(); // encrypted token/keys (disimpan terenkripsi)
            $table->string('status_callback_url')->nullable();
            $table->boolean('sync_orders')->default(true);
            $table->boolean('sync_stock')->default(true);
            $table->datetime('last_synced_at')->nullable();
            $table->enum('status', ['active', 'inactive', 'error'])->default('active');
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['platform', 'status']);
        });

        // Mapping produk master ↔ produk channel
        Schema::create('marketplace_product_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('channel_product_id')->nullable(); // SKU di channel
            $table->string('channel_url')->nullable();
            $table->decimal('channel_price', 15, 2)->nullable();
            $table->decimal('channel_stock', 15, 2)->nullable();
            $table->datetime('last_synced_at')->nullable();
            $table->enum('sync_status', ['pending', 'synced', 'error', 'disabled'])->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_channel_id', 'product_id'], 'mps_channel_product_unique');
            $table->index('sync_status');
        });

        // Order dari marketplace → konversi ke sales order
        Schema::create('marketplace_orders', function (Blueprint $table) {
            $table->id();
            $table->string('channel_order_id')->nullable();
            $table->string('channel_order_number')->nullable();
            $table->foreignId('marketplace_channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->string('customer_phone')->nullable();
            $table->text('shipping_address')->nullable();
            $table->string('courier_name')->nullable();
            $table->string('tracking_number')->nullable();
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->json('payload')->nullable(); // payload mentah channel
            $table->enum('status', [
                'new', 'confirmed', 'converted', 'shipped', 'delivered', 'cancelled', 'failed',
            ])->default('new');
            $table->datetime('synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['marketplace_channel_id', 'status']);
            $table->index('channel_order_id');
        });

        // Log sinkronisasi (audit)
        Schema::create('marketplace_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_channel_id')->constrained()->cascadeOnDelete();
            $table->enum('direction', ['in', 'out']);
            $table->enum('entity', ['order', 'stock', 'price']);
            $table->enum('status', ['success', 'failed', 'skipped']);
            $table->string('reference')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_sync_logs');
        Schema::dropIfExists('marketplace_orders');
        Schema::dropIfExists('marketplace_product_syncs');
        Schema::dropIfExists('marketplace_channels');
    }
};

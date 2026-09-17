<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master kurir/ekspedisi (PRD 4.7 Could Have)
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->default('')->unique();
            $table->string('name');
            $table->enum('type', ['internal', 'external']);
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->decimal('cost_per_kg', 12, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Surat jalan / pengiriman (PRD 4.7 Should Have)
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 50)->unique();
            $table->foreignId('sales_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name')->default('');
            $table->string('recipient_phone')->nullable();
            $table->text('destination_address')->nullable();
            $table->decimal('total_weight_kg', 10, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->enum('status', ['preparing', 'dispatched', 'in_transit', 'delivered', 'cancelled'])
                ->default('preparing');
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sales_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
        Schema::dropIfExists('couriers');
    }
};

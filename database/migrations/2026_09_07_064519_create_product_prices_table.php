<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('price_type');
            $table->decimal('price', 15, 2);
            $table->decimal('min_quantity', 15, 2)->default(1);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'price_type']);
            $table->index('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};

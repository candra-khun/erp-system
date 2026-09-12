<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->date('summary_date');
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->decimal('total_purchases', 15, 2)->default(0);
            $table->decimal('total_returns', 15, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->decimal('cash_in', 15, 2)->default(0);
            $table->decimal('cash_out', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'summary_date']);
        });

        Schema::create('report_product_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('period_type', 10);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('qty_sold', 15, 2)->default(0);
            $table->decimal('revenue', 15, 2)->default(0);
            $table->decimal('qty_purchased', 15, 2)->default(0);
            $table->decimal('purchase_cost', 15, 2)->default(0);
            $table->timestamps();

            $table->index(['product_id', 'period_type', 'period_start'], 'idx_prod_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_product_summaries');
        Schema::dropIfExists('report_daily_summaries');
    }
};

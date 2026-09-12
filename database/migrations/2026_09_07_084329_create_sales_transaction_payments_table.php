<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'transfer', 'card', 'other']);
            $table->decimal('amount', 15, 2);
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('sales_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_transaction_payments');
    }
};

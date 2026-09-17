<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment gateway module (PRD Fase 4 #14).
     *
     * Provider-agnostic: gateway_credentials menyimpan kredensial per provider,
     * gateway_transactions mencatat siklus hidup QR/charge/refund.
     * Implementasi provider konkret di App\Services\PaymentGateway\*.
     */
    public function up(): void
    {
        // Kredensial gateway (per provider + gudang)
        Schema::create('gateway_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // midtrans, xendit, manual, ...
            $table->string('merchant_id')->nullable();
            $table->string('api_key')->nullable();
            $table->string('server_key')->nullable();
            $table->text('additional_config')->nullable(); // JSON
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('sandbox_mode')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider', 'is_active']);
        });

        // Transaksi gateway (QRIS, VA, card, dll)
        Schema::create('gateway_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 60)->unique(); // GT-...
            $table->string('gateway_transaction_id')->nullable()->unique(); // id dari provider
            $table->string('provider');
            $table->foreignId('gateway_credential_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('payable'); // sales_transaction, sales_order, account_receivable
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_channel')->nullable(); // qris, va_bca, credit_card, ...
            $table->string('payment_reference')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('fee_amount', 15, 2)->default(0);
            $table->decimal('settlement_amount', 15, 2)->default(0);
            $table->string('currency', 10)->default('IDR');
            $table->json('payload_request')->nullable();
            $table->json('payload_response')->nullable();
            $table->enum('status', [
                'pending', 'created', 'paid', 'settlement', 'deny', 'expire', 'cancel', 'refund', 'failed',
            ])->default('pending');
            $table->datetime('expires_at')->nullable();
            $table->datetime('paid_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['provider', 'status']);
            $table->index('payable_type', 'payable_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_transactions');
        Schema::dropIfExists('gateway_credentials');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of Accounts (COA) - referenced by journal lines
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'equity', 'revenue', 'expense']);
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('accounts')->nullOnDelete();
        });

        // Accounts Payable (Hutang ke Supplier)
        Schema::create('account_payables', function (Blueprint $table) {
            $table->id();
            $table->string('ap_number', 50)->unique();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('reference'); // purchase_order, goods_receipt, etc.
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2);
            $table->date('due_date');
            $table->enum('status', ['open', 'partial', 'paid', 'overdue'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Accounts Receivable (Piutang dari Customer)
        Schema::create('account_receivables', function (Blueprint $table) {
            $table->id();
            $table->string('ar_number', 50)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('reference'); // sales_order, sales_transaction, etc.
            $table->decimal('total_amount', 15, 2);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('remaining_amount', 15, 2);
            $table->date('due_date');
            $table->enum('status', ['open', 'partial', 'paid', 'overdue'])->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // Cash/Bank Transactions
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_number', 50)->unique();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->enum('category', ['sales', 'purchase', 'operational', 'salary', 'other']);
            $table->decimal('amount', 15, 2);
            $table->string('payment_method', 50)->default('cash'); // cash, bank_transfer, etc.
            $table->morphs('reference'); // nullable reference to source transaction
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('transaction_date');
            $table->timestamps();
            $table->softDeletes();
        });

        // Journal Entries (Double Entry)
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->string('journal_number', 50)->unique();
            $table->date('journal_date');
            $table->enum('type', ['general', 'sales', 'purchase', 'adjustment', 'closing']);
            $table->nullableMorphs('reference'); // source transaction
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_posted')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // Journal Entry Lines
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->enum('type', ['debit', 'credit']);
            $table->decimal('amount', 15, 2);
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('cash_transactions');
        Schema::dropIfExists('account_receivables');
        Schema::dropIfExists('account_payables');
        Schema::dropIfExists('accounts');
    }
};

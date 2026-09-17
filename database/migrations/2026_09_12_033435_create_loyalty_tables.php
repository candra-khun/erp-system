<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Program loyalitas: poin pelanggan (PRD 4.6 Should Have)
        Schema::create('loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['earn', 'redeem', 'adjust', 'expire']);
            $table->decimal('points', 12, 0);
            $table->decimal('balance_after', 12, 0);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'type']);
            $table->index(['reference_type', 'reference_id']);
        });

        // Tier member pelanggan (bronze/silver/gold) berdasarkan akumulasi poin
        Schema::create('customer_loyalty_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->enum('tier', ['bronze', 'silver', 'gold'])->default('bronze');
            $table->decimal('points_balance', 12, 0)->default(0);
            $table->decimal('lifetime_points', 12, 0)->default(0);
            $table->date('tier_achieved_at')->nullable();
            $table->timestamps();
        });

        // Riwayat komunikasi dengan pelanggan (PRD 4.6 Could Have)
        Schema::create('customer_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['phone', 'whatsapp', 'email', 'visit', 'other']);
            $table->string('subject')->default('');
            $table->text('summary');
            $table->text('follow_up_notes')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_communications');
        Schema::dropIfExists('customer_loyalty_profiles');
        Schema::dropIfExists('loyalty_points');
    }
};

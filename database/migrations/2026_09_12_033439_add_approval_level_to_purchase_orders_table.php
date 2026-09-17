<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Approval berjenjang PO berdasarkan nominal (PRD 4.3 Should Have):
        // level 1: PO <= threshold_cabang disetujui admin_cabang/staff_pembelian
        // level 2: PO > threshold_cabang butuh approval tambahan finance/owner
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->tinyInteger('approval_level')->default(0)->after('status');
            $table->foreignId('second_approved_by')->nullable()->constrained('users')->nullOnDelete()->after('approved_by');
            $table->timestamp('second_approved_at')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['approval_level', 'second_approved_by', 'second_approved_at']);
        });
    }
};

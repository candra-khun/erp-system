<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Tambahkan status pending_level2 untuk approval berjenjang PO (PRD 4.3)
        DB::statement(
            "ALTER TABLE purchase_orders MODIFY COLUMN status ENUM(
                'draft', 'submitted', 'pending_level2', 'approved', 'sent_to_supplier',
                'partial_received', 'received', 'completed', 'cancelled'
            ) NOT NULL DEFAULT 'draft'"
        );
    }

    public function down(): void
    {
        DB::statement(
            "ALTER TABLE purchase_orders MODIFY COLUMN status ENUM(
                'draft', 'submitted', 'approved', 'sent_to_supplier',
                'partial_received', 'received', 'completed', 'cancelled'
            ) NOT NULL DEFAULT 'draft'"
        );
    }
};

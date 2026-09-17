<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR & Payroll module (PRD Fase 4 #12).
     *
     * Struktur: employees (master) → payroll_periods (periode gaji) →
     * payrolls (per karyawan, per periode) → payroll_components
     * (gaji pokok, tunjangan, potongan, THR, dll).
     */
    public function up(): void
    {
        // Master Data Karyawan
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number')->unique();
            $table->string('full_name');
            $table->string('id_card_number')->nullable()->unique(); // NIK KTP
            $table->string('npwp')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('resign_date')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->enum('payment_type', ['monthly', 'daily', 'hourly'])->default('monthly');
            $table->enum('tax_status', ['non_taxable', 'taxable'])->default('non_taxable');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('warehouse_id');
            $table->index('is_active');
        });

        // Periode Penggajian (per bulan + gudang/cabang)
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('period_number', 50)->unique(); // PY-202609-0001
            $table->integer('year');
            $table->integer('month'); // 1-12
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('payment_date')->nullable();
            $table->enum('status', ['draft', 'generated', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['year', 'month', 'warehouse_id']);
            $table->index('status');
        });

        // Penggajian per karyawan dalam satu periode
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->string('payroll_number', 50)->unique();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('basic_salary', 15, 2)->default(0);
            $table->decimal('total_earnings', 15, 2)->default(0);   // pendapatan
            $table->decimal('total_deductions', 15, 2)->default(0); // potongan
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('net_salary', 15, 2)->default(0);
            $table->decimal('working_days', 15, 2)->default(0);
            $table->decimal('overtime_hours', 15, 2)->default(0);
            $table->decimal('overtime_amount', 15, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index('status');
        });

        // Komponen gaji: tunjangan & potongan
        Schema::create('payroll_components', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['earning', 'deduction', 'tax']);
            $table->enum('calculation_type', ['fixed', 'percentage_of_basic', 'manual']);
            $table->decimal('amount', 15, 2)->default(0);
            $table->decimal('percentage', 5, 2)->default(0);
            $table->boolean('is_taxable')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'is_active']);
        });

        // Detail komponen yang dikenakan ke payroll karyawan
        Schema::create('payroll_component_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_component_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name'); // snapshot nama komponen
            $table->enum('type', ['earning', 'deduction', 'tax']);
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // Pengajuan cuti/absensi (sederhana)
        Schema::create('employee_leaves', function (Blueprint $table) {
            $table->id();
            $table->string('leave_number', 50)->unique();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['annual', 'sick', 'unpaid', 'other']);
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days', 8, 2)->default(1);
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_leaves');
        Schema::dropIfExists('payroll_component_lines');
        Schema::dropIfExists('payroll_components');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('employees');
    }
};

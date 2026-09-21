<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul Absensi (PRD: HR & Payroll — Manajemen Kehadiran).
     *
     * Struktur: work_shifts (definisi shift) → employee_shift_schedules
     * (penugasan shift per tanggal) → attendances (data clock in/out harian)
     * → attendance_validations (bukti device: fingerprint/GPS/selfie).
     * overtime_requests = pengajuan lembur berapproval, holidays = kalender
     * libur nasional + weekly off.
     */
    public function up(): void
    {
        // Definisi Shift Kerja (pagi/siang/malam, atau jam reguler kantor)
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // Shift Pagi / Shift Reguler
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('late_tolerance_minutes')->default(0); // toleransi terlambat
            $table->unsignedInteger('early_leave_tolerance_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        // Default shift karyawan (shortcut; penugasan harian ada di schedule)
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('work_shift_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained('work_shifts')
                ->nullOnDelete();
        });

        // Penugasan shift per karyawan per tanggal (bisa recurring mingguan)
        Schema::create('employee_shift_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_shift_id')->constrained()->cascadeOnDelete();
            $table->date('effective_date');                 // tanggal mulai berlaku
            $table->date('end_date')->nullable();           // null = berlaku sampai diubah
            $table->enum('source', ['manual', 'recurring'])->default('manual');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'effective_date']);
            $table->index('effective_date');
        });

        // Data Kehadiran harian (satu baris per karyawan per hari)
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->string('attendance_number', 50)->unique(); // ATT-20260917-0001
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->foreignId('work_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();
            $table->enum('status', ['present', 'late', 'absent', 'on_leave', 'holiday'])
                ->default('present');
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('early_out_minutes')->default(0);
            $table->decimal('overtime_hours', 8, 2)->default(0); // jam lembur (dari clock out atau overtime_request)
            $table->enum('check_in_method', ['manual', 'pin', 'barcode', 'fingerprint', 'gps', 'selfie', 'web'])
                ->default('manual');
            $table->enum('check_out_method', ['manual', 'pin', 'barcode', 'fingerprint', 'gps', 'selfie', 'web'])
                ->default('manual');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('selfie_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['attendance_date', 'status']);
        });

        // Bukti verifikasi absensi dari device (fingerprint/GPS/selfie) — interface terbuka
        Schema::create('attendance_validations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained()->cascadeOnDelete();
            $table->enum('method', ['pin', 'barcode', 'fingerprint', 'gps', 'selfie']);
            $table->string('device_id')->nullable();       // id mesin fingerprint / device mobile
            $table->string('direction')->default('in');    // in / out
            $table->dateTime('validated_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('location_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['attendance_id', 'direction']);
        });

        // Pengajuan Lembur (overtime) — butuh approval atasan
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->string('overtime_number', 50)->unique(); // OT-20260917-0001
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('overtime_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('hours', 6, 2)->default(0);
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'status']);
            $table->index('overtime_date');
        });

        // Kalender Hari Libur (nasional/agama/perusahaan + weekly off)
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('holiday_date')->nullable();       // null untuk weekly_off
            $table->enum('type', ['national', 'religious', 'company', 'weekly_off'])->default('national');
            $table->boolean('is_recurring_annual')->default(false); // tiap tahun tanggal sama
            $table->unsignedInteger('weekly_day_of_week')->nullable(); // 0=Minggu, untuk weekly_off
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('holiday_date');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('overtime_requests');
        Schema::dropIfExists('attendance_validations');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('employee_shift_schedules');

        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('work_shift_id');
        });

        Schema::dropIfExists('work_shifts');
    }
};

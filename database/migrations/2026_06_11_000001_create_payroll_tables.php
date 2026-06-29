<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('teacher_salaries')) {
            Schema::create('teacher_salaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teacher_id')->unique()->constrained('teachers')->cascadeOnDelete();
                $table->decimal('gaji_pokok', 15, 2)->default(0);
                $table->decimal('potongan_per_absen', 15, 2)->default(0);
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('payroll_periods')) {
            Schema::create('payroll_periods', function (Blueprint $table) {
                $table->id();
                $table->unsignedSmallInteger('tahun');
                $table->unsignedTinyInteger('bulan');
                $table->date('tanggal_mulai');
                $table->date('tanggal_selesai');
                $table->enum('status', ['draft', 'final'])->default('draft');
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['tahun', 'bulan'], 'payroll_periods_tahun_bulan_unique');
            });
        }

        if (!Schema::hasTable('payroll_items')) {
            Schema::create('payroll_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
                $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
                $table->decimal('gaji_pokok', 15, 2)->default(0);
                $table->decimal('potongan_absen', 15, 2)->default(0);
                $table->decimal('tambahan_infal', 15, 2)->default(0);
                $table->decimal('gaji_bersih', 15, 2)->default(0);
                $table->unsignedInteger('jumlah_absen_diganti')->default(0);
                $table->unsignedInteger('jumlah_mengganti')->default(0);
                $table->text('catatan')->nullable();
                $table->timestamps();

                $table->unique(['payroll_period_id', 'teacher_id'], 'payroll_items_period_teacher_unique');
            });
        }

        if (!Schema::hasTable('payroll_item_details')) {
            Schema::create('payroll_item_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
                $table->foreignId('leave_request_id')->nullable()->constrained('leave_requests')->nullOnDelete();
                $table->date('tanggal_event')->nullable();
                $table->enum('tipe', ['potongan_absen', 'tambahan_infal']);
                $table->decimal('nominal', 15, 2)->default(0);
                $table->text('keterangan')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_item_details');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('teacher_salaries');
    }
};

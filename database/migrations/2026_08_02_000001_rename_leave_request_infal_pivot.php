<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| Penyeragaman skema pivot infal
|--------------------------------------------------------------------------
|
| Project sistem-absensi-sekolah-hayyan sudah memakai tabel
| `leave_request_infal` dengan kolom `status_infal` / `catatan_infal`, dan
| tabel itu sudah berisi data di produksi shared hosting yang tidak punya
| akses SSH. Menyeragamkan ke nama tersebut membuat produksi tidak perlu
| diubah sama sekali, sementara project ini masih tahap pengembangan.
|
| Migrasi ini idempotent: menangani database yang masih memakai nama lama,
| database yang belum punya keduanya, maupun yang sudah memakai nama baru.
|
*/
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_request_infal')) {
            $this->normalizeColumnNames('leave_request_infal');

            return;
        }

        if (Schema::hasTable('leave_request_infal_teacher')) {
            Schema::rename('leave_request_infal_teacher', 'leave_request_infal');
            $this->normalizeColumnNames('leave_request_infal');

            return;
        }

        Schema::create('leave_request_infal', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->enum('status_infal', ['pending', 'disetujui', 'ditolak'])->default('pending');
            $table->text('catatan_infal')->nullable();
            $table->timestamps();
            $table->unique(['leave_request_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('leave_request_infal')) {
            return;
        }

        if (Schema::hasColumn('leave_request_infal', 'status_infal')) {
            DB::statement("ALTER TABLE `leave_request_infal` CHANGE `status_infal` `status` ENUM('pending','disetujui','ditolak') NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasColumn('leave_request_infal', 'catatan_infal')) {
            DB::statement('ALTER TABLE `leave_request_infal` CHANGE `catatan_infal` `catatan` TEXT NULL');
        }

        Schema::rename('leave_request_infal', 'leave_request_infal_teacher');
    }

    private function normalizeColumnNames(string $table): void
    {
        if (Schema::hasColumn($table, 'status') && ! Schema::hasColumn($table, 'status_infal')) {
            DB::statement("ALTER TABLE `{$table}` CHANGE `status` `status_infal` ENUM('pending','disetujui','ditolak') NOT NULL DEFAULT 'pending'");
        }

        if (Schema::hasColumn($table, 'catatan') && ! Schema::hasColumn($table, 'catatan_infal')) {
            DB::statement("ALTER TABLE `{$table}` CHANGE `catatan` `catatan_infal` TEXT NULL");
        }
    }
};

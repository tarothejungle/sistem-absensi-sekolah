<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }

    public function up(): void
    {
        if (!Schema::hasTable('attendances')) {
            return;
        }

        if (!Schema::hasColumn('attendances', 'attendance_session_id')) {
            Schema::table('attendances', function ($table) {
                $table->unsignedBigInteger('attendance_session_id')->nullable()->after('teacher_id');
            });
        }

        // Index biasa untuk teacher_id wajib ada sebelum unique lama dihapus,
        // supaya foreign key teacher_id tetap punya index sendiri.
        if (!$this->indexExists('attendances', 'attendances_teacher_id_index')) {
            DB::statement('ALTER TABLE `attendances` ADD INDEX `attendances_teacher_id_index` (`teacher_id`)');
        }

        if (!$this->indexExists('attendances', 'attendances_attendance_session_id_index')) {
            DB::statement('ALTER TABLE `attendances` ADD INDEX `attendances_attendance_session_id_index` (`attendance_session_id`)');
        }

        // Hapus unique lama teacher_id + tanggal kalau masih ada.
        if ($this->indexExists('attendances', 'attendances_teacher_id_tanggal_unique')) {
            DB::statement('ALTER TABLE `attendances` DROP INDEX `attendances_teacher_id_tanggal_unique`');
        }

        // Tambahkan unique baru untuk mendukung multi sesi.
        if (!$this->indexExists('attendances', 'attendances_teacher_date_session_unique')) {
            DB::statement('ALTER TABLE `attendances` ADD UNIQUE `attendances_teacher_date_session_unique` (`teacher_id`, `tanggal`, `attendance_session_id`)');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendances')) {
            return;
        }

        if ($this->indexExists('attendances', 'attendances_teacher_date_session_unique')) {
            DB::statement('ALTER TABLE `attendances` DROP INDEX `attendances_teacher_date_session_unique`');
        }

        if (!$this->indexExists('attendances', 'attendances_teacher_id_tanggal_unique')) {
            DB::statement('ALTER TABLE `attendances` ADD UNIQUE `attendances_teacher_id_tanggal_unique` (`teacher_id`, `tanggal`)');
        }

        if ($this->indexExists('attendances', 'attendances_attendance_session_id_index')) {
            DB::statement('ALTER TABLE `attendances` DROP INDEX `attendances_attendance_session_id_index`');
        }

        if ($this->indexExists('attendances', 'attendances_teacher_id_index')) {
            DB::statement('ALTER TABLE `attendances` DROP INDEX `attendances_teacher_id_index`');
        }
    }
};

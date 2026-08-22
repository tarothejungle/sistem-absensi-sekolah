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
        if (Schema::hasTable('attendance_session_teacher')) {
            if (!$this->indexExists('attendance_session_teacher', 'ast_unique_session_teacher')) {
                DB::statement('ALTER TABLE `attendance_session_teacher` ADD UNIQUE `ast_unique_session_teacher` (`attendance_session_id`, `teacher_id`)');
            }
        }

        if (Schema::hasTable('attendances')) {
            if (!$this->indexExists('attendances', 'attendances_teacher_date_session_unique')) {
                if ($this->indexExists('attendances', 'attendances_teacher_id_tanggal_unique')) {
                    DB::statement('ALTER TABLE `attendances` DROP INDEX `attendances_teacher_id_tanggal_unique`');
                }

                DB::statement('ALTER TABLE `attendances` ADD UNIQUE `attendances_teacher_date_session_unique` (`teacher_id`, `tanggal`, `attendance_session_id`)');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_session_teacher')) {
            if ($this->indexExists('attendance_session_teacher', 'ast_unique_session_teacher')) {
                DB::statement('ALTER TABLE `attendance_session_teacher` DROP INDEX `ast_unique_session_teacher`');
            }
        }

        if (Schema::hasTable('attendances')) {
            if ($this->indexExists('attendances', 'attendances_teacher_date_session_unique')) {
                DB::statement('ALTER TABLE `attendances` DROP INDEX `attendances_teacher_date_session_unique`');
            }
        }
    }
};

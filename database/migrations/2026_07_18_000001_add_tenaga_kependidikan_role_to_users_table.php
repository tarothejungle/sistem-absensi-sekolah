<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('guru','bendahara','kepala_sekolah','tenaga_kependidikan','super_admin') NOT NULL DEFAULT 'guru'");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::table('users')
            ->where('role', 'tenaga_kependidikan')
            ->update(['role' => 'guru']);

        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('guru','bendahara','kepala_sekolah','super_admin') NOT NULL DEFAULT 'guru'");
    }
};

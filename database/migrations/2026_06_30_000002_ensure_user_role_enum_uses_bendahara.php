<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'role')) {
            return;
        }

        $this->setRoleEnum(['guru', 'admin', 'bendahara', 'kepala_sekolah', 'super_admin']);

        DB::table('users')
            ->where('role', 'admin')
            ->update(['role' => 'bendahara']);

        $this->setRoleEnum(['guru', 'bendahara', 'kepala_sekolah', 'super_admin']);
    }

    public function down(): void
    {
        // Migrasi ini hanya memastikan skema database lama mengikuti role aktif aplikasi.
    }

    private function setRoleEnum(array $roles): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $values = collect($roles)
            ->map(fn (string $role) => "'" . str_replace("'", "''", $role) . "'")
            ->implode(',');

        DB::statement("ALTER TABLE `users` MODIFY `role` ENUM({$values}) NOT NULL DEFAULT 'guru'");
    }
};

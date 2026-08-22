<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances') || ! Schema::hasColumn('attendances', 'verification_method')) {
            return;
        }

        DB::statement("ALTER TABLE attendances MODIFY verification_method ENUM('face', 'face_match_browser', 'face_match_fastapi', 'fingerprint', 'system_auto') NOT NULL DEFAULT 'face'");
        DB::table('attendances')
            ->where('device_info', 'system-auto-status')
            ->whereNull('check_in_time')
            ->whereNull('check_out_time')
            ->update(['verification_method' => 'system_auto']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendances') || ! Schema::hasColumn('attendances', 'verification_method')) {
            return;
        }

        DB::table('attendances')->where('verification_method', 'system_auto')->update(['verification_method' => 'face']);
        DB::statement("ALTER TABLE attendances MODIFY verification_method ENUM('face', 'face_match_browser', 'face_match_fastapi', 'fingerprint') NOT NULL DEFAULT 'face'");
    }
};

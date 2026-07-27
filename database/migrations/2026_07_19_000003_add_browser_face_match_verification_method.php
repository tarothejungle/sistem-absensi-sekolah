<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE attendances MODIFY verification_method ENUM('face', 'face_match_browser', 'fingerprint') NOT NULL DEFAULT 'face'");
    }

    public function down(): void
    {
        DB::statement("UPDATE attendances SET verification_method = 'face' WHERE verification_method = 'face_match_browser'");
        DB::statement("ALTER TABLE attendances MODIFY verification_method ENUM('face', 'fingerprint') NOT NULL DEFAULT 'face'");
    }
};

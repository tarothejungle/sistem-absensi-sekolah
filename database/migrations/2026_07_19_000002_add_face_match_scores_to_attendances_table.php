<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->decimal('check_in_face_match_score', 6, 4)->nullable()->after('check_in_face_photo');
            $table->decimal('check_out_face_match_score', 6, 4)->nullable()->after('check_out_face_photo');
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropColumn(['check_in_face_match_score', 'check_out_face_match_score']);
        });
    }
};

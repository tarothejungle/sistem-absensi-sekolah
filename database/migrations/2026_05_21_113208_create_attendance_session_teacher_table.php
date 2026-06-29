<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (!Schema::hasColumn('attendances', 'attendance_session_id')) {
                $table->foreignId('attendance_session_id')
                    ->nullable()
                    ->after('teacher_id')
                    ->constrained('attendance_sessions')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            if (Schema::hasColumn('attendances', 'attendance_session_id')) {
                $table->dropForeign(['attendance_session_id']);
                $table->dropColumn('attendance_session_id');
            }
        });
    }
};
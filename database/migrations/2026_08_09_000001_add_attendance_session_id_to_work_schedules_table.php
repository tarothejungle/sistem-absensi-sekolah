<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('work_schedules', 'attendance_session_id')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->unsignedBigInteger('attendance_session_id')->nullable()->after('teacher_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('work_schedules', 'attendance_session_id')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->dropColumn('attendance_session_id');
            });
        }
    }
};
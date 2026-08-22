<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_session_teacher')) {
            return;
        }

        if (!Schema::hasColumn('attendance_session_teacher', 'is_primary')) {
            Schema::table('attendance_session_teacher', function (Blueprint $table) {
                $table->boolean('is_primary')->default(false)->after('teacher_id');
            });
        }

        // Backfill: set first session per teacher as primary if none exists
        $teacherIds = DB::table('attendance_session_teacher')
            ->select('teacher_id')
            ->distinct()
            ->pluck('teacher_id');

        foreach ($teacherIds as $teacherId) {
            $hasPrimary = DB::table('attendance_session_teacher')
                ->where('teacher_id', $teacherId)
                ->where('is_primary', true)
                ->exists();

            if (!$hasPrimary) {
                $first = DB::table('attendance_session_teacher')
                    ->where('teacher_id', $teacherId)
                    ->orderBy('id')
                    ->first();

                if ($first) {
                    DB::table('attendance_session_teacher')
                        ->where('id', $first->id)
                        ->update(['is_primary' => true]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_session_teacher') && Schema::hasColumn('attendance_session_teacher', 'is_primary')) {
            Schema::table('attendance_session_teacher', function (Blueprint $table) {
                $table->dropColumn('is_primary');
            });
        }
    }
};

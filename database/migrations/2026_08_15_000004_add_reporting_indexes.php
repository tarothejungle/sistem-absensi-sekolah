<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', fn (Blueprint $table) => $table
                ->index(['tanggal', 'status_kehadiran'], 'attendances_date_status_report_index'));
        }
        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', fn (Blueprint $table) => $table
                ->index(['status_pengajuan', 'tanggal_mulai', 'tanggal_selesai'], 'leave_requests_status_dates_index'));
        }
        if (Schema::hasTable('leave_request_infal')) {
            Schema::table('leave_request_infal', fn (Blueprint $table) => $table
                ->index(['status_infal', 'teacher_id'], 'leave_infal_status_teacher_index'));
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendances')) {
            Schema::table('attendances', fn (Blueprint $table) => $table->dropIndex('attendances_date_status_report_index'));
        }
        if (Schema::hasTable('leave_requests')) {
            Schema::table('leave_requests', fn (Blueprint $table) => $table->dropIndex('leave_requests_status_dates_index'));
        }
        if (Schema::hasTable('leave_request_infal')) {
            Schema::table('leave_request_infal', fn (Blueprint $table) => $table->dropIndex('leave_infal_status_teacher_index'));
        }
    }
};

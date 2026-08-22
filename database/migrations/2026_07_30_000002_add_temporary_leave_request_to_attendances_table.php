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
        if (!Schema::hasColumn('attendances', 'temporary_leave_request_id')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->unsignedBigInteger('temporary_leave_request_id')->nullable()->after('work_schedule_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('attendances', 'temporary_leave_request_id')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropColumn('temporary_leave_request_id');
            });
        }
    }
};
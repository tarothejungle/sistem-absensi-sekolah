<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_attendance_reminders', function (Blueprint $table) {
            $table->timestamp('last_attempted_at')->nullable()->after('reminder_type');
            $table->unsignedInteger('attempt_count')->default(0)->after('last_attempted_at');
        });
    }

    public function down(): void
    {
        Schema::table('push_attendance_reminders', function (Blueprint $table) {
            $table->dropColumn(['last_attempted_at', 'attempt_count']);
        });
    }
};

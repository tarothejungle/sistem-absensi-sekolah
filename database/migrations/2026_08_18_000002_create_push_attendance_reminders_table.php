<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_attendance_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->constrained('attendance_sessions')->cascadeOnDelete();
            $table->date('reminder_date');
            $table->string('reminder_type', 20);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'attendance_session_id', 'reminder_date', 'reminder_type'], 'push_attendance_reminder_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_attendance_reminders');
    }
};

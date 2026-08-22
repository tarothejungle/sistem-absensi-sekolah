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
        if (!Schema::hasTable('attendance_session_duty_schedule')) {
            Schema::create('attendance_session_duty_schedule', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('duty_schedule_id');
                $table->unsignedBigInteger('attendance_session_id');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_session_duty_schedule');
    }
};
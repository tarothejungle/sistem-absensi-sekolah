<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_session_teacher')) {
            return;
        }

        Schema::create('attendance_session_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_session_id')
                ->constrained('attendance_sessions')
                ->cascadeOnDelete();
            $table->foreignId('teacher_id')
                ->constrained('teachers')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['attendance_session_id', 'teacher_id'],
                'attendance_session_teacher_unique'
            );
        });
    }

    public function down(): void
    {
        // Keberadaan tabel ini sekarang menjadi bagian skema inti.
        // Migrasi ini hanya memastikan database lama ikut tersinkron.
    }
};

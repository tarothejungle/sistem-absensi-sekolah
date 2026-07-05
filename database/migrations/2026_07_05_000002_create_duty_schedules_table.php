<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_schedules', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal')->unique();
            $table->string('nama_piket', 120)->nullable();
            $table->text('keterangan')->nullable();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
        });

        Schema::create('duty_schedule_teacher', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duty_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['duty_schedule_id', 'teacher_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_schedule_teacher');
        Schema::dropIfExists('duty_schedules');
    }
};

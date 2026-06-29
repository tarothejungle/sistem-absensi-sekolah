<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('nama_sesi', 100);
            $table->time('jam_masuk');
            $table->time('jam_pulang');
            $table->integer('toleransi_terlambat')->default(15);
            $table->time('batas_check_in_mulai');
            $table->time('batas_check_in_selesai');
            $table->time('batas_check_out_mulai');
            $table->time('batas_check_out_selesai');
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
    }
};
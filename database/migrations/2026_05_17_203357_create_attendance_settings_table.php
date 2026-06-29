<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();
            $table->time('jam_masuk')->default('07:00:00');
            $table->time('jam_pulang')->default('15:00:00');
            $table->integer('toleransi_terlambat')->default(15);
            $table->time('batas_check_in_mulai')->default('05:00:00');
            $table->time('batas_check_in_selesai')->default('10:00:00');
            $table->time('batas_check_out_mulai')->default('12:00:00');
            $table->time('batas_check_out_selesai')->default('18:00:00');
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
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
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->foreignId('infal_teacher_id')
                ->nullable()
                ->after('teacher_id')
                ->constrained('teachers')
                ->nullOnDelete();

            $table->enum('status_infal', ['pending', 'disetujui', 'ditolak'])
                ->default('pending')
                ->after('status_pengajuan');

            $table->text('catatan_infal')->nullable()->after('status_infal');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['infal_teacher_id']);
            $table->dropColumn(['infal_teacher_id', 'status_infal', 'catatan_infal']);
        });
    }
};

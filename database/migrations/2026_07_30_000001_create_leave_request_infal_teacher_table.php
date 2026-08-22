<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('leave_request_infal_teacher')) {
            Schema::create('leave_request_infal_teacher', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('leave_request_id')->constrained()->cascadeOnDelete();
                $table->foreignId('teacher_id')->constrained()->restrictOnDelete();
                $table->enum('status', ['pending', 'disetujui', 'ditolak'])->default('pending');
                $table->text('catatan')->nullable();
                $table->timestamps();
                $table->unique(['leave_request_id', 'teacher_id']);
            });
        }

        if (Schema::hasColumn('leave_requests', 'infal_teacher_id')) {
            DB::table('leave_requests')
                ->whereNotNull('infal_teacher_id')
                ->orderBy('id')
                ->chunkById(500, function ($leaves): void {
                    $now = now();
                    $rows = $leaves->map(fn ($leave): array => [
                        'leave_request_id' => $leave->id,
                        'teacher_id' => $leave->infal_teacher_id,
                        'status' => $leave->status_infal ?? 'pending',
                        'catatan' => $leave->catatan_infal,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all();

                    DB::table('leave_request_infal_teacher')->insertOrIgnore($rows);
                });

            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->dropForeign(['infal_teacher_id']);
                $table->dropColumn(['infal_teacher_id', 'status_infal', 'catatan_infal']);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('leave_requests', 'infal_teacher_id')) {
            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->foreignId('infal_teacher_id')->nullable()->after('teacher_id');
                $table->enum('status_infal', ['pending', 'disetujui', 'ditolak'])->default('pending')->after('status_pengajuan');
                $table->text('catatan_infal')->nullable()->after('status_infal');
            });

            DB::table('leave_request_infal_teacher')
                ->orderBy('leave_request_id')
                ->orderByRaw("CASE status WHEN 'disetujui' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
                ->orderBy('teacher_id')
                ->get()
                ->groupBy('leave_request_id')
                ->each(function ($infals, $leaveRequestId): void {
                    $infal = $infals->first();

                    DB::table('leave_requests')->where('id', $leaveRequestId)->update([
                        'infal_teacher_id' => $infal->teacher_id,
                        'status_infal' => $infal->status,
                        'catatan_infal' => $infal->catatan,
                    ]);
                });

            Schema::table('leave_requests', function (Blueprint $table): void {
                $table->foreign('infal_teacher_id')->references('id')->on('teachers')->nullOnDelete();
            });
        }

        Schema::dropIfExists('leave_request_infal_teacher');
    }
};

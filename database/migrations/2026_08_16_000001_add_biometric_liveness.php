<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_liveness_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('purpose', 20);
            $table->json('action_sequence');
            $table->string('method', 50);
            $table->string('status', 20)->default('pending');
            $table->string('failure_reason', 100)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'expires_at'], 'blc_user_status_expiry_idx');
            $table->index(['status', 'created_at'], 'blc_status_created_idx');
        });

        Schema::table('teacher_face_enrollments', function (Blueprint $table): void {
            $table->uuid('liveness_challenge_id')->nullable()->after('teacher_id');
            $table->foreign('liveness_challenge_id', 'tfe_liveness_challenge_fk')
                ->references('id')->on('biometric_liveness_challenges')->nullOnDelete();
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->uuid('check_in_liveness_challenge_id')->nullable()->after('check_in_face_match_score');
            $table->uuid('check_out_liveness_challenge_id')->nullable()->after('check_out_face_match_score');
            $table->foreign('check_in_liveness_challenge_id', 'att_check_in_liveness_fk')
                ->references('id')->on('biometric_liveness_challenges')->nullOnDelete();
            $table->foreign('check_out_liveness_challenge_id', 'att_check_out_liveness_fk')
                ->references('id')->on('biometric_liveness_challenges')->nullOnDelete();
        });

        DB::statement("ALTER TABLE attendances MODIFY verification_method ENUM('face', 'face_match_browser', 'face_match_fastapi', 'face_liveness_fastapi', 'fingerprint', 'system_auto') NOT NULL DEFAULT 'face'");
    }

    public function down(): void
    {
        DB::table('attendances')
            ->where('verification_method', 'face_liveness_fastapi')
            ->update(['verification_method' => 'face_match_fastapi']);
        DB::statement("ALTER TABLE attendances MODIFY verification_method ENUM('face', 'face_match_browser', 'face_match_fastapi', 'fingerprint', 'system_auto') NOT NULL DEFAULT 'face'");

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropForeign('att_check_in_liveness_fk');
            $table->dropForeign('att_check_out_liveness_fk');
            $table->dropColumn(['check_in_liveness_challenge_id', 'check_out_liveness_challenge_id']);
        });

        Schema::table('teacher_face_enrollments', function (Blueprint $table): void {
            $table->dropForeign('tfe_liveness_challenge_fk');
            $table->dropColumn('liveness_challenge_id');
        });

        Schema::dropIfExists('biometric_liveness_challenges');
    }
};

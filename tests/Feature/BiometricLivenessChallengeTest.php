<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\BiometricLivenessChallenge;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BiometricLivenessChallengeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_teacher_can_issue_enrollment_challenge(): void
    {
        [$user, $teacher] = $this->createTeacher();

        $response = $this->actingAs($user)->postJson(route('biometric.liveness.challenges.store'), [
            'purpose' => BiometricLivenessChallenge::PURPOSE_ENROLLMENT,
        ]);

        $response->assertOk()
            ->assertJsonPath('method', 'active_head_pose_v1')
            ->assertJsonCount(4, 'steps');

        $this->assertDatabaseHas('biometric_liveness_challenges', [
            'user_id' => $user->id,
            'teacher_id' => $teacher->id,
            'purpose' => BiometricLivenessChallenge::PURPOSE_ENROLLMENT,
            'status' => BiometricLivenessChallenge::STATUS_PENDING,
        ]);
    }

    public function test_teacher_can_issue_attendance_challenge_for_assigned_session(): void
    {
        [$user, $teacher] = $this->createTeacher();
        $session = AttendanceSession::create([
            'nama_sesi' => 'Sesi Liveness',
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '15:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '06:00:00',
            'batas_check_in_selesai' => '08:00:00',
            'batas_check_out_mulai' => '14:00:00',
            'batas_check_out_selesai' => '16:00:00',
            'status' => 'aktif',
        ]);
        $teacher->attendanceSessions()->attach($session->id, ['is_primary' => true]);

        $this->actingAs($user)
            ->postJson(route('biometric.liveness.challenges.store'), [
                'purpose' => BiometricLivenessChallenge::PURPOSE_CHECK_IN,
                'attendance_session_id' => $session->id,
            ])
            ->assertOk();

        $this->assertDatabaseHas('biometric_liveness_challenges', [
            'purpose' => BiometricLivenessChallenge::PURPOSE_CHECK_IN,
            'attendance_session_id' => $session->id,
        ]);
    }

    public function test_non_teacher_cannot_issue_challenge(): void
    {
        $user = User::create([
            'username' => uniqid('admin_', true),
            'name' => 'Admin Challenge Test',
            'email' => uniqid('admin_challenge_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['role' => 'super_admin', 'status' => 'aktif'])->save();

        $this->actingAs($user)
            ->postJson(route('biometric.liveness.challenges.store'), ['purpose' => BiometricLivenessChallenge::PURPOSE_ENROLLMENT])
            ->assertForbidden();
    }

    private function createTeacher(): array
    {
        $user = User::create([
            'username' => uniqid('liv_', true),
            'name' => 'Guru Challenge Test',
            'email' => uniqid('teacher_challenge_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['role' => 'guru', 'status' => 'aktif'])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'nama_lengkap' => 'Guru Challenge Test']);

        return [$user, $teacher];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\TeacherFaceEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithLiveness;
use Tests\TestCase;

class SelfFaceEnrollmentTest extends TestCase
{
    use DatabaseTransactions;
    use InteractsWithLiveness;

    public function test_all_attendance_roles_can_enroll_their_own_face(): void
    {
        foreach (Teacher::DATA_GURU_ROLES as $role) {
            [$teacher, $user] = $this->createTeacherUser($role, 'Enrollment '.$role);
            $this->fakeLivenessEnrollment(0.01);

            $this->actingAs($user)
                ->post(route('profile.face-enrollment.store'), $this->livenessPayload($user, $teacher, \App\Models\BiometricLivenessChallenge::PURPOSE_ENROLLMENT))
                ->assertRedirect(route('profile.edit'));

            $this->assertDatabaseHas('teacher_face_enrollments', [
                'teacher_id' => $teacher->id,
                'enrolled_by' => $user->id,
                'recapture_count' => 0,
            ]);
        }
    }

    public function test_liveness_challenge_requests_do_not_consume_face_enrollment_limit(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Enrollment Rate Limit Terpisah');

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($user)
                ->postJson(route('biometric.liveness.challenges.store'), [
                    'purpose' => \App\Models\BiometricLivenessChallenge::PURPOSE_ENROLLMENT,
                ])
                ->assertOk();
        }

        $this->fakeLivenessEnrollment(0.01);

        $this->actingAs($user)
            ->postJson(
                route('profile.face-enrollment.store'),
                $this->livenessPayload(
                    $user,
                    $teacher,
                    \App\Models\BiometricLivenessChallenge::PURPOSE_ENROLLMENT,
                ),
            )
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_face_enrollment_rate_limit_returns_actionable_json(): void
    {
        [, $user] = $this->createTeacherUser('guru', 'Enrollment Rate Limit Message');

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->actingAs($user)
                ->postJson(route('profile.face-enrollment.store'), [])
                ->assertUnprocessable();
        }

        $this->actingAs($user)
            ->postJson(route('profile.face-enrollment.store'), [])
            ->assertTooManyRequests()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('code', 'rate_limited')
            ->assertJsonPath('retry_after', 60)
            ->assertJsonPath(
                'message',
                'Percobaan pendaftaran wajah dilakukan terlalu sering. Tunggu 60 detik lalu coba kembali.',
            );
    }

    public function test_attendance_role_profile_displays_liveness_enrollment(): void
    {
        [, $user] = $this->createTeacherUser('guru', 'Profil Enrollment');

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Wajah Absensi')
            ->assertSee('active liveness')
            ->assertSee('Mulai Liveness')
            ->assertSee('liveness_challenge_id', false)
            ->assertDontSee('face_image', false);
    }

    public function test_super_admin_profile_does_not_display_self_enrollment(): void
    {
        $user = $this->createUser('super_admin', 'Admin Tanpa Enrollment Mandiri');

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('Daftarkan Wajah Terpilih');
    }

    public function test_enrollment_is_locked_after_first_template(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Kuota Recapture');
        $this->fakeLivenessEnrollment(0.01);

        $this->actingAs($user)
            ->post(route('profile.face-enrollment.store'), $this->livenessPayload($user, $teacher, \App\Models\BiometricLivenessChallenge::PURPOSE_ENROLLMENT))
            ->assertSessionHasNoErrors();

        $enrollment = $teacher->faceEnrollment()->firstOrFail();
        $this->assertTrue($enrollment->isLocked());

        Http::fake();
        $this->actingAs($user)
            ->post(route('profile.face-enrollment.store'), $this->livenessPayload($user, $teacher, \App\Models\BiometricLivenessChallenge::PURPOSE_ENROLLMENT))
            ->assertSessionHasErrors(['liveness_frames' => 'Template wajah sudah dikunci. Hubungi super admin untuk mereset data wajah sebelum mendaftar ulang.']);

        Http::assertSentCount(1);
        $this->assertSame(0, $enrollment->fresh()->recapture_count);
    }

    public function test_locked_enrollment_does_not_call_face_service(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Enrollment Terkunci');
        TeacherFaceEnrollment::create([
            'teacher_id' => $teacher->id,
            'face_descriptor' => array_fill(0, TeacherFaceEnrollment::EMBEDDING_DIMENSIONS, 0.01),
            'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            'recapture_count' => 1,
            'enrolled_by' => $user->id,
            'enrolled_at' => now(),
        ]);
        Http::fake();

        $this->actingAs($user)
            ->post(route('profile.face-enrollment.store'), $this->livenessPayload($user, $teacher, \App\Models\BiometricLivenessChallenge::PURPOSE_ENROLLMENT))
            ->assertSessionHasErrors('liveness_frames');

        Http::assertNothingSent();
        $this->assertTrue($teacher->faceEnrollment()->firstOrFail()->isLocked());
    }

    public function test_locked_enrollment_returns_structured_json_error(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Enrollment JSON Terkunci');
        TeacherFaceEnrollment::create([
            'teacher_id' => $teacher->id,
            'face_descriptor' => array_fill(0, TeacherFaceEnrollment::EMBEDDING_DIMENSIONS, 0.01),
            'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            'recapture_count' => 0,
            'enrolled_by' => $user->id,
            'enrolled_at' => now(),
        ]);

        Http::fake();

        $this->actingAs($user)
            ->postJson(route('profile.face-enrollment.store'), $this->livenessPayload($user, $teacher, \App\Models\BiometricLivenessChallenge::PURPOSE_ENROLLMENT))
            ->assertUnprocessable()
            ->assertJson([
                'ok' => false,
                'code' => 'enrollment_locked',
            ]);

        Http::assertNothingSent();
    }

    public function test_user_without_attendance_role_cannot_enroll_face(): void
    {
        $user = $this->createUser('super_admin', 'Bukan Pemilik Absensi');

        $this->actingAs($user)
            ->post(route('profile.face-enrollment.store'))
            ->assertForbidden();
    }

    public function test_teacher_cannot_reset_own_face_enrollment(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Tidak Bisa Reset Sendiri');
        TeacherFaceEnrollment::create([
            'teacher_id' => $teacher->id,
            'face_descriptor' => array_fill(0, TeacherFaceEnrollment::EMBEDDING_DIMENSIONS, 0.01),
            'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            'recapture_count' => 3,
            'enrolled_by' => $user->id,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($user)
            ->delete(route('admin.teachers.face-enrollment.delete', $teacher))
            ->assertForbidden();

        $this->assertDatabaseHas('teacher_face_enrollments', ['teacher_id' => $teacher->id]);
    }

    private function createTeacherUser(string $role, string $name): array
    {
        $user = $this->createUser($role, $name);
        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => $name,
        ]);

        return [$teacher, $user];
    }

    private function createUser(string $role, string $name): User
    {
        $user = new User([
            'username' => uniqid('face_', true),
            'name' => $name,
            'password' => 'Strong!Password123',
        ]);
        $user->role = $role;
        $user->status = 'aktif';
        $user->save();

        return $user;
    }
}

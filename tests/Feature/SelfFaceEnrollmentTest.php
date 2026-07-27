<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\TeacherFaceEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SelfFaceEnrollmentTest extends TestCase
{
    use DatabaseTransactions;

    public function test_all_attendance_roles_can_enroll_their_own_face(): void
    {
        foreach (Teacher::DATA_GURU_ROLES as $role) {
            [$teacher, $user] = $this->createTeacherUser($role, 'Enrollment '.$role);
            $this->fakeEnrollmentResponse(0.01);

            $this->actingAs($user)
                ->post(route('profile.face-enrollment.store'), ['face_image' => $this->validFaceImage()])
                ->assertRedirect(route('profile.edit'));

            $this->assertDatabaseHas('teacher_face_enrollments', [
                'teacher_id' => $teacher->id,
                'enrolled_by' => $user->id,
                'recapture_count' => 0,
            ]);
        }
    }

    public function test_three_successful_recaptures_are_allowed_and_fourth_is_rejected(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Kuota Recapture');
        $this->fakeEnrollmentResponse(0.01);

        $this->actingAs($user)
            ->post(route('profile.face-enrollment.store'), ['face_image' => $this->validFaceImage()])
            ->assertSessionHasNoErrors();

        foreach ([0.02, 0.03, 0.04] as $value) {
            $this->fakeEnrollmentResponse($value);
            $this->actingAs($user)
                ->post(route('profile.face-enrollment.store'), ['face_image' => $this->validFaceImage()])
                ->assertSessionHasNoErrors();
        }

        $enrollment = $teacher->faceEnrollment()->firstOrFail();
        $this->assertSame(3, $enrollment->recapture_count);
        $this->assertSame(0, $enrollment->remainingRecaptures());

        Http::fake();
        $this->actingAs($user)
            ->post(route('profile.face-enrollment.store'), ['face_image' => $this->validFaceImage()])
            ->assertSessionHasErrors('face_image');

        Http::assertSentCount(1);
        $this->assertSame(3, $enrollment->fresh()->recapture_count);
    }

    public function test_failed_recapture_does_not_reduce_quota(): void
    {
        [$teacher, $user] = $this->createTeacherUser('guru', 'Recapture Gagal');
        TeacherFaceEnrollment::create([
            'teacher_id' => $teacher->id,
            'face_descriptor' => array_fill(0, TeacherFaceEnrollment::EMBEDDING_DIMENSIONS, 0.01),
            'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            'recapture_count' => 1,
            'enrolled_by' => $user->id,
            'enrolled_at' => now(),
        ]);
        Http::fake([
            '*/api/v1/faces/enroll' => Http::response(['detail' => 'Wajah tidak terdeteksi.'], 422),
        ]);

        $this->actingAs($user)
            ->post(route('profile.face-enrollment.store'), ['face_image' => $this->validFaceImage()])
            ->assertSessionHasErrors('face_image');

        $this->assertSame(1, $teacher->faceEnrollment()->firstOrFail()->recapture_count);
    }

    public function test_user_without_attendance_role_cannot_enroll_face(): void
    {
        $user = $this->createUser('super_admin', 'Bukan Pemilik Absensi');

        $this->actingAs($user)
            ->post(route('profile.face-enrollment.store'), ['face_image' => $this->validFaceImage()])
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

    private function fakeEnrollmentResponse(float $value): void
    {
        config([
            'services.face.url' => 'http://127.0.0.1:8001',
            'services.face.api_key' => 'test-secret',
        ]);
        Http::fake([
            '*/api/v1/faces/enroll' => Http::response([
                'embedding' => array_fill(0, TeacherFaceEnrollment::EMBEDDING_DIMENSIONS, $value),
                'dimensions' => TeacherFaceEnrollment::EMBEDDING_DIMENSIONS,
                'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            ]),
        ]);
    }

    private function validFaceImage(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
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
            'nip' => uniqid('face_', true),
            'name' => $name,
            'password' => 'Strong!Password123',
        ]);
        $user->role = $role;
        $user->status = 'aktif';
        $user->save();

        return $user;
    }
}

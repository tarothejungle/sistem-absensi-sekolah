<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\TeacherFaceEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BiometricStatusTest extends TestCase
{
    use DatabaseTransactions;

    public function test_status_reports_ready_service_and_compatible_enrollment(): void
    {
        [$user] = $this->createTeacherUser(true);
        $this->fakeHealth(true, TeacherFaceEnrollment::MODEL_VERSION);

        $this->actingAs($user)
            ->getJson(route('biometric.status'))
            ->assertOk()
            ->assertJsonPath('ready', true)
            ->assertJsonPath('service.state', 'ready')
            ->assertJsonPath('service.liveness_ready', true)
            ->assertJsonPath('service.liveness_method', 'active_head_pose_v1')
            ->assertJsonPath('service.quality_ready', true)
            ->assertJsonPath('service.quality_method', 'landmark_2d_106')
            ->assertJsonPath('enrollment.compatible', true);
    }

    public function test_status_allows_attendance_face_match_when_liveness_is_unavailable(): void
    {
        [$user] = $this->createTeacherUser(true);
        $this->fakeHealth(true, TeacherFaceEnrollment::MODEL_VERSION, false);

        $this->actingAs($user)
            ->getJson(route('biometric.status'))
            ->assertOk()
            ->assertJsonPath('ready', true)
            ->assertJsonPath('service.ready', true)
            ->assertJsonPath('service.liveness_ready', false)
            ->assertJsonPath('enrollment.compatible', true);
    }

    public function test_status_fails_closed_when_service_is_unavailable(): void
    {
        [$user] = $this->createTeacherUser(true);
        config([
            'services.face.url' => 'http://127.0.0.1:8001',
            'services.face.api_key' => 'test-secret',
        ]);
        Http::fake(fn () => Http::failedConnection());

        $this->actingAs($user)
            ->getJson(route('biometric.status'))
            ->assertOk()
            ->assertJsonPath('ready', false)
            ->assertJsonPath('service.state', 'unavailable');
    }

    public function test_status_fails_closed_when_face_quality_model_is_unavailable(): void
    {
        [$user] = $this->createTeacherUser(true);
        $this->fakeHealth(true, TeacherFaceEnrollment::MODEL_VERSION, true, false);

        $this->actingAs($user)
            ->getJson(route('biometric.status'))
            ->assertOk()
            ->assertJsonPath('ready', false)
            ->assertJsonPath('service.state', 'quality_unavailable')
            ->assertJsonPath('service.quality_ready', false);
    }

    public function test_status_rejects_model_version_mismatch(): void
    {
        [$user] = $this->createTeacherUser(true);
        $this->fakeHealth(true, 'different-model');

        $this->actingAs($user)
            ->getJson(route('biometric.status'))
            ->assertOk()
            ->assertJsonPath('ready', false)
            ->assertJsonPath('service.state', 'model_mismatch');
    }

    public function test_status_requires_face_enrollment_even_when_service_is_ready(): void
    {
        [$user] = $this->createTeacherUser(false);
        $this->fakeHealth(true, TeacherFaceEnrollment::MODEL_VERSION);

        $this->actingAs($user)
            ->getJson(route('biometric.status'))
            ->assertOk()
            ->assertJsonPath('ready', false)
            ->assertJsonPath('service.ready', true)
            ->assertJsonPath('enrollment.registered', false);
    }

    private function fakeHealth(
        bool $loaded,
        string $modelVersion,
        bool $livenessReady = true,
        bool $qualityReady = true,
    ): void
    {
        config([
            'services.face.url' => 'http://127.0.0.1:8001',
            'services.face.api_key' => 'test-secret',
        ]);

        Http::fake([
            'http://127.0.0.1:8001/health' => Http::response([
                'status' => $loaded ? 'ok' : 'starting',
                'model_loaded' => $loaded,
                'model_version' => $modelVersion,
                'liveness_ready' => $loaded && $livenessReady,
                'liveness_method' => config('biometrics.liveness_method'),
                'quality_ready' => $loaded && $qualityReady,
                'quality_method' => 'landmark_2d_106',
            ]),
        ]);
    }

    private function createTeacherUser(bool $withEnrollment): array
    {
        $user = User::create([
            'username' => uniqid('bio_', true),
            'name' => 'Guru Biometrik',
            'email' => uniqid('bio_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['role' => 'guru', 'status' => 'aktif'])->save();

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Guru Biometrik '.uniqid(),
            'email' => uniqid('teacher_bio_', true).'@example.test',
        ]);

        if ($withEnrollment) {
            TeacherFaceEnrollment::create([
                'teacher_id' => $teacher->id,
                'face_descriptor' => array_fill(0, TeacherFaceEnrollment::EMBEDDING_DIMENSIONS, 0.01),
                'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
                'enrolled_by' => $user->id,
                'enrolled_at' => now(),
            ]);
        }

        return [$user, $teacher];
    }
}

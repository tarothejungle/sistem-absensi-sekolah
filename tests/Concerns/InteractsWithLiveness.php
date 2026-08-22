<?php

namespace Tests\Concerns;

use App\Models\BiometricLivenessChallenge;
use App\Models\Teacher;
use App\Models\TeacherFaceEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\Http;

trait InteractsWithLiveness
{
    protected function livenessPayload(
        User $user,
        Teacher $teacher,
        string $purpose,
        ?int $attendanceSessionId = null,
    ): array {
        if ($purpose !== BiometricLivenessChallenge::PURPOSE_ENROLLMENT && ! config('biometrics.liveness_required')) {
            return ['face_image' => $this->validFaceImage()];
        }

        $challenge = BiometricLivenessChallenge::create([
            'user_id' => $user->id,
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $attendanceSessionId,
            'purpose' => $purpose,
            'action_sequence' => ['center', 'turn_left', 'turn_right', 'center'],
            'method' => config('biometrics.liveness_method'),
            'status' => BiometricLivenessChallenge::STATUS_PENDING,
            'expires_at' => now()->addMinute(),
        ]);

        return [
            'liveness_challenge_id' => $challenge->id,
            'liveness_frames' => $this->validLivenessFrames(),
        ];
    }

    protected function validLivenessFrames(): array
    {
        $image = $this->validFaceImage();

        return [$image, $image, $image, $image];
    }

    protected function validFaceImage(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
    }

    protected function fakeLivenessEnrollment(float $value = 0.01): void
    {
        $this->configureFaceService();
        Http::fake([
            '*/api/v1/faces/liveness/enroll' => Http::response([
                'embedding' => array_fill(0, TeacherFaceEnrollment::EMBEDDING_DIMENSIONS, $value),
                'dimensions' => TeacherFaceEnrollment::EMBEDDING_DIMENSIONS,
                'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
                'audit_frame_index' => 3,
                'liveness' => [
                    'passed' => true,
                    'method' => config('biometrics.liveness_method'),
                    'reason' => null,
                ],
            ]),
        ]);
    }

    protected function fakeFaceVerification(bool $matched = true, float $distance = 0.2): void
    {
        $this->configureFaceService();
        Http::fake([
            '*/api/v1/faces/verify' => Http::response([
                'matched' => $matched,
                'distance' => $distance,
                'threshold' => 0.45,
                'faces_detected' => 1,
                'model_version' => TeacherFaceEnrollment::MODEL_VERSION,
            ]),
        ]);
    }

    protected function fakeLivenessVerification(bool $matched = true, float $distance = 0.2): void
    {
        $this->configureFaceService();
        Http::fake([
            '*/api/v1/faces/liveness/verify' => Http::response([
                'matched' => $matched,
                'distance' => $distance,
                'audit_frame_index' => 3,
                'liveness' => ['passed' => true, 'method' => config('biometrics.liveness_method')],
            ]),
        ]);
    }

    private function configureFaceService(): void
    {
        config([
            'services.face.url' => 'http://127.0.0.1:8001',
            'services.face.api_key' => 'test-secret',
        ]);
    }
}

<?php

namespace Tests\Unit;

use Tests\TestCase;

class BrowserFaceApiAssetTest extends TestCase
{
    public function test_browser_face_api_bundle_contains_the_liveness_networks(): void
    {
        $assetPath = public_path('js/face-api.min.js');
        $asset = file_get_contents($assetPath);

        $this->assertIsString($asset);
        $this->assertGreaterThan(100_000, strlen($asset));
        $this->assertStringContainsString('tinyFaceDetector', $asset);
        $this->assertStringContainsString('faceLandmark68Net', $asset);
        $this->assertStringContainsString('loadFromUri', $asset);
    }

    public function test_attendance_supports_required_liveness_with_local_face_match_fallback(): void
    {
        $attendance = file_get_contents(public_path('js/attendance.js'));
        $enrollment = file_get_contents(public_path('js/face-enrollment.js'));

        $this->assertIsString($attendance);
        $this->assertStringContainsString("data.set('face_image', biometricPayload.faceImage)", $attendance);
        $this->assertStringContainsString('BiometricLiveness.captureFrame', $attendance);
        $this->assertStringContainsString('BiometricLiveness.run', $attendance);
        $this->assertStringContainsString("data.set('liveness_challenge_id'", $attendance);
        $this->assertStringContainsString("data.append('liveness_frames[]'", $attendance);
        $this->assertStringContainsString("'face_occluded'", $attendance);
        $this->assertStringContainsString("'face_partial'", $attendance);
        $this->assertStringContainsString("'face_blurry'", $attendance);

        $this->assertIsString($enrollment);
        $this->assertStringContainsString('BiometricLiveness.run', $enrollment);
    }
}

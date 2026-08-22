<?php

namespace App\Services;

use App\Exceptions\FaceVerificationException;
use App\Models\BiometricLivenessChallenge;
use App\Models\TeacherFaceEnrollment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FaceVerificationService
{
    private const MAX_IMAGE_BYTES = 2_097_152;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function saveBase64Image(?string $base64Image, string $folder): ?string
    {
        $decoded = $this->decodeImage($base64Image);

        if (! $decoded) {
            return null;
        }

        $filename = trim($folder, '/').'/'.date('YmdHis').'_'.uniqid('', true).'.'.$decoded['extension'];
        Storage::disk('local')->put($filename, $decoded['binary']);

        return $filename;
    }

    public function isValidImage(?string $base64Image): bool
    {
        return $this->decodeImage($base64Image) !== null;
    }

    public function health(): array
    {
        $url = rtrim((string) config('services.face.url'), '/');
        $apiKey = (string) config('services.face.api_key');

        if ($url === '' || $apiKey === '') {
            return $this->healthResult(
                false,
                'misconfigured',
                'Layanan biometrik belum dikonfigurasi lengkap.'
            );
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(min(2, max(1, (int) config('services.face.connect_timeout', 3))))
                ->timeout(min(3, max(1, (int) config('services.face.timeout', 15))))
                ->get($url.'/health');
        } catch (ConnectionException $exception) {
            Log::warning('Unable to check face service health.', ['message' => $exception->getMessage()]);

            return $this->healthResult(
                false,
                'unavailable',
                'FastAPI/InsightFace tidak dapat dihubungi. Pastikan container face-api berjalan.'
            );
        }

        if (! $response->successful()) {
            return $this->healthResult(
                false,
                'unavailable',
                'FastAPI/InsightFace merespons dengan status yang tidak sehat.'
            );
        }

        $modelLoaded = $response->json('model_loaded') === true;
        $modelVersion = $response->json('model_version');
        $livenessReady = $response->json('liveness_ready') === true;
        $livenessMethod = $response->json('liveness_method');
        $qualityReady = $response->json('quality_ready') === true;
        $qualityMethod = $response->json('quality_method');

        if (! $modelLoaded) {
            return $this->healthResult(
                false,
                'starting',
                'Model InsightFace sedang dimuat. Tunggu sebentar lalu periksa kembali.',
                is_string($modelVersion) ? $modelVersion : null,
            );
        }

        if ($modelVersion !== TeacherFaceEnrollment::MODEL_VERSION) {
            return $this->healthResult(
                false,
                'model_mismatch',
                'Versi model FastAPI tidak kompatibel dengan template wajah aplikasi.',
                is_string($modelVersion) ? $modelVersion : null,
            );
        }

        if (! $qualityReady) {
            return $this->healthResult(
                false,
                'quality_unavailable',
                'Model pemeriksaan kejelasan dan visibilitas wajah belum siap.',
                $modelVersion,
                is_string($livenessMethod) ? $livenessMethod : null,
                false,
                is_string($qualityMethod) ? $qualityMethod : null,
            );
        }

        $livenessAvailable = $livenessReady
            && $livenessMethod === config('biometrics.liveness_method');

        return $this->healthResult(
            true,
            'ready',
            $livenessAvailable
                ? 'FastAPI dan InsightFace siap untuk face match; active liveness enrollment juga siap.'
                : 'FastAPI dan InsightFace siap untuk face match. Active liveness enrollment belum siap.',
            $modelVersion,
            is_string($livenessMethod) ? $livenessMethod : null,
            $livenessAvailable,
            is_string($qualityMethod) ? $qualityMethod : null,
            true,
        );
    }

    public function readiness(?TeacherFaceEnrollment $enrollment): array
    {
        $service = $this->health();
        $registered = $enrollment !== null;
        $compatible = $enrollment?->isCompatible() ?? false;

        $message = $service['message'];
        if (! $registered) {
            $message = 'Wajah belum terdaftar. Daftarkan wajah melalui menu profil.';
        } elseif (! $compatible) {
            $message = 'Template wajah tidak kompatibel dan harus didaftarkan ulang.';
        }

        return [
            'ready' => $service['ready'] && $compatible,
            'message' => $message,
            'service' => $service,
            'enrollment' => [
                'registered' => $registered,
                'compatible' => $compatible,
                'model_version' => $enrollment?->model_version,
                'enrolled_at' => $enrollment?->enrolled_at?->toIso8601String(),
            ],
        ];
    }

    public function enroll(?string $base64Image): array
    {
        $this->assertValidImage($base64Image);
        $response = $this->request('/api/v1/faces/enroll', [
            'image' => $base64Image,
        ]);
        $embedding = $response->json('embedding');
        $modelVersion = $response->json('model_version');

        if (! $this->isValidEmbedding($embedding) || $modelVersion !== TeacherFaceEnrollment::MODEL_VERSION) {
            Log::warning('Face service returned an invalid enrollment response.');
            throw new FaceVerificationException(
                'invalid_service_response',
                'Layanan verifikasi wajah memberikan respons tidak valid.'
            );
        }

        return [
            'embedding' => array_map('floatval', array_values($embedding)),
            'model_version' => $modelVersion,
        ];
    }

    public function verify(?string $base64Image, TeacherFaceEnrollment $enrollment): array
    {
        $this->assertValidImage($base64Image);

        if ($enrollment->model_version !== TeacherFaceEnrollment::MODEL_VERSION ||
            ! $this->isValidEmbedding($enrollment->face_descriptor)) {
            throw new FaceVerificationException(
                'model_mismatch',
                'Template wajah lama tidak kompatibel. Hubungi admin untuk mendaftarkan ulang wajah.'
            );
        }

        $response = $this->request('/api/v1/faces/verify', [
            'image' => $base64Image,
            'embedding' => array_values($enrollment->face_descriptor),
            'model_version' => $enrollment->model_version,
        ]);
        $matched = $response->json('matched');
        $distance = $response->json('distance');

        if (! is_bool($matched) || ! is_numeric($distance)) {
            Log::warning('Face service returned an invalid verification response.');
            throw new FaceVerificationException(
                'invalid_service_response',
                'Layanan verifikasi wajah memberikan respons tidak valid.'
            );
        }

        return ['matched' => $matched, 'distance' => (float) $distance];
    }

    public function enrollLiveness(array $frames, BiometricLivenessChallenge $challenge): array
    {
        $payload = $this->livenessPayload($frames, $challenge);
        $response = $this->request('/api/v1/faces/liveness/enroll', $payload);
        $embedding = $response->json('embedding');
        $modelVersion = $response->json('model_version');
        $auditFrameIndex = $response->json('audit_frame_index');

        if (! $this->isValidEmbedding($embedding)
            || $modelVersion !== TeacherFaceEnrollment::MODEL_VERSION
            || ! is_int($auditFrameIndex)
            || ! ($response->json('liveness.passed') === true)) {
            Log::warning('Face service returned an invalid liveness enrollment response.');
            throw new FaceVerificationException(
                'invalid_service_response',
                'Layanan liveness memberikan respons enrollment tidak valid.',
            );
        }

        return [
            'embedding' => array_map('floatval', array_values($embedding)),
            'model_version' => $modelVersion,
            'audit_frame_index' => $auditFrameIndex,
        ];
    }

    public function verifyLiveness(
        array $frames,
        TeacherFaceEnrollment $enrollment,
        BiometricLivenessChallenge $challenge,
    ): array {
        if ($enrollment->model_version !== TeacherFaceEnrollment::MODEL_VERSION
            || ! $this->isValidEmbedding($enrollment->face_descriptor)) {
            throw new FaceVerificationException(
                'model_mismatch',
                'Template wajah lama tidak kompatibel. Hubungi admin untuk mendaftarkan ulang wajah.',
            );
        }

        $response = $this->request('/api/v1/faces/liveness/verify', array_merge(
            $this->livenessPayload($frames, $challenge),
            [
                'embedding' => array_values($enrollment->face_descriptor),
                'model_version' => $enrollment->model_version,
            ],
        ));
        $matched = $response->json('matched');
        $distance = $response->json('distance');
        $auditFrameIndex = $response->json('audit_frame_index');

        if (! is_bool($matched)
            || ! is_numeric($distance)
            || ! is_int($auditFrameIndex)
            || ! ($response->json('liveness.passed') === true)) {
            Log::warning('Face service returned an invalid liveness verification response.');
            throw new FaceVerificationException(
                'invalid_service_response',
                'Layanan liveness memberikan respons verifikasi tidak valid.',
            );
        }

        return [
            'matched' => $matched,
            'distance' => (float) $distance,
            'audit_frame_index' => $auditFrameIndex,
        ];
    }

    private function request(string $path, array $payload): Response
    {
        $url = rtrim((string) config('services.face.url'), '/');
        $apiKey = (string) config('services.face.api_key');

        if ($url === '' || $apiKey === '') {
            throw new FaceVerificationException(
                'service_misconfigured',
                'Layanan verifikasi wajah belum dikonfigurasi.'
            );
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->connectTimeout((int) config('services.face.connect_timeout', 3))
                ->timeout((int) config('services.face.timeout', 15))
                ->post($url.$path, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to face service.', ['message' => $exception->getMessage()]);
            throw new FaceVerificationException(
                'service_unavailable',
                'Layanan verifikasi wajah sedang tidak tersedia. Silakan coba kembali.',
                true,
                $exception,
            );
        }

        if ($response->successful()) {
            return $response;
        }

        $message = $response->json('detail.message');
        $reason = $response->json('detail.code');
        Log::warning('Face service rejected a request.', [
            'status' => $response->status(),
            'code' => $response->json('detail.code'),
        ]);

        if ($response->status() === 422 && is_string($message) && $message !== '') {
            throw new FaceVerificationException(
                is_string($reason) && $reason !== '' ? $reason : 'face_rejected',
                $message,
                true,
            );
        }

        if ($response->status() === 429) {
            throw new FaceVerificationException(
                'rate_limited',
                'Terlalu banyak permintaan verifikasi wajah. Tunggu sebentar lalu coba kembali.',
                true,
            );
        }

        if (in_array($response->status(), [401, 403, 404, 410], true)) {
            throw new FaceVerificationException(
                'service_misconfigured',
                'Konfigurasi endpoint verifikasi wajah tidak sesuai. Hubungi admin untuk memperbaikinya.',
                false,
            );
        }

        throw new FaceVerificationException(
            'service_unavailable',
            'Layanan verifikasi wajah sedang tidak tersedia. Silakan coba kembali.',
            true,
        );
    }

    private function assertValidImage(?string $base64Image): void
    {
        if (! $this->isValidImage($base64Image)) {
            throw new FaceVerificationException(
                'invalid_image',
                'Foto wajah tidak valid. Pastikan kamera aktif lalu coba lagi.',
                true,
            );
        }
    }

    private function livenessPayload(array $frames, BiometricLivenessChallenge $challenge): array
    {
        $expectedCount = (int) config('biometrics.frame_count', 4);
        if (count($frames) !== $expectedCount || count($challenge->action_sequence ?? []) !== $expectedCount) {
            throw new FaceVerificationException(
                'liveness_frame_count',
                'Sequence liveness tidak lengkap. Mulai pemindaian ulang.',
                true,
            );
        }

        $payloadFrames = [];
        foreach (array_values($frames) as $index => $frame) {
            if (! is_string($frame)) {
                throw new FaceVerificationException('invalid_image', 'Frame liveness tidak valid.', true);
            }
            $this->assertValidImage($frame);
            $payloadFrames[] = [
                'step' => $challenge->action_sequence[$index],
                'image' => $frame,
            ];
        }

        return [
            'challenge_id' => $challenge->id,
            'method' => $challenge->method,
            'frames' => $payloadFrames,
        ];
    }

    private function healthResult(
        bool $ready,
        string $state,
        string $message,
        ?string $modelVersion = null,
        ?string $livenessMethod = null,
        bool $livenessReady = false,
        ?string $qualityMethod = null,
        bool $qualityReady = false,
    ): array {
        return [
            'ready' => $ready,
            'state' => $state,
            'message' => $message,
            'model_version' => $modelVersion,
            'liveness_method' => $livenessMethod,
            'liveness_ready' => $livenessReady,
            'quality_method' => $qualityMethod,
            'quality_ready' => $qualityReady,
        ];
    }

    private function isValidEmbedding(mixed $embedding): bool
    {
        return is_array($embedding)
            && count($embedding) === TeacherFaceEnrollment::EMBEDDING_DIMENSIONS
            && ! collect($embedding)->contains(fn ($value) => ! is_numeric($value) || ! is_finite((float) $value));
    }

    private function decodeImage(?string $base64Image): ?array
    {
        if (! $base64Image || ! preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $base64Image)) {
            return null;
        }

        [, $content] = explode('base64,', $base64Image, 2);
        $binary = base64_decode($content, true);

        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $imageInfo = @getimagesizefromstring($binary);
        $mime = $imageInfo['mime'] ?? null;

        if (! $mime || ! isset(self::MIME_EXTENSIONS[$mime])) {
            return null;
        }

        return [
            'binary' => $binary,
            'extension' => self::MIME_EXTENSIONS[$mime],
        ];
    }
}

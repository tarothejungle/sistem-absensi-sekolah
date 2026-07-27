<?php

namespace App\Services;

use App\Models\TeacherFaceEnrollment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

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
            throw new RuntimeException('Layanan verifikasi wajah memberikan respons tidak valid.');
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
            throw new RuntimeException('Template wajah lama tidak kompatibel. Hubungi admin untuk mendaftarkan ulang wajah.');
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
            throw new RuntimeException('Layanan verifikasi wajah memberikan respons tidak valid.');
        }

        return ['matched' => $matched, 'distance' => (float) $distance];
    }

    private function request(string $path, array $payload): Response
    {
        $url = rtrim((string) config('services.face.url'), '/');
        $apiKey = (string) config('services.face.api_key');

        if ($url === '' || $apiKey === '') {
            throw new RuntimeException('Layanan verifikasi wajah belum dikonfigurasi.');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['X-API-Key' => $apiKey])
                ->connectTimeout((int) config('services.face.connect_timeout', 3))
                ->timeout((int) config('services.face.timeout', 15))
                ->post($url.$path, $payload);
        } catch (ConnectionException $exception) {
            Log::warning('Unable to connect to face service.', ['message' => $exception->getMessage()]);
            throw new RuntimeException('Layanan verifikasi wajah sedang tidak tersedia. Silakan coba kembali.');
        }

        if ($response->successful()) {
            return $response;
        }

        $message = $response->json('detail.message');
        Log::warning('Face service rejected a request.', [
            'status' => $response->status(),
            'code' => $response->json('detail.code'),
        ]);

        if ($response->status() === 422 && is_string($message) && $message !== '') {
            throw new RuntimeException($message);
        }

        throw new RuntimeException('Layanan verifikasi wajah sedang tidak tersedia. Silakan coba kembali.');
    }

    private function assertValidImage(?string $base64Image): void
    {
        if (! $this->isValidImage($base64Image)) {
            throw new RuntimeException('Foto wajah tidak valid. Pastikan kamera aktif lalu coba lagi.');
        }
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

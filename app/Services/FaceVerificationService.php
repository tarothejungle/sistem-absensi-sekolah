<?php

namespace App\Services;

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

        if (!$decoded) {
            return null;
        }

        $filename = trim($folder, '/') . '/' . date('YmdHis') . '_' . uniqid('', true) . '.' . $decoded['extension'];
        Storage::disk('local')->put($filename, $decoded['binary']);

        return $filename;
    }

    public function verify(?string $base64Image): bool
    {
        // Placeholder hemat biaya: memastikan foto wajah/webcam terkirim.
        // Untuk face recognition sungguhan, panggil API Python/FastAPI di sini.
        return $this->decodeImage($base64Image) !== null;
    }

    private function decodeImage(?string $base64Image): ?array
    {
        if (!$base64Image || !preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $base64Image)) {
            return null;
        }

        [, $content] = explode('base64,', $base64Image, 2);
        $binary = base64_decode($content, true);

        if ($binary === false || $binary === '' || strlen($binary) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $imageInfo = @getimagesizefromstring($binary);
        $mime = $imageInfo['mime'] ?? null;

        if (!$mime || !isset(self::MIME_EXTENSIONS[$mime])) {
            return null;
        }

        return [
            'binary' => $binary,
            'extension' => self::MIME_EXTENSIONS[$mime],
        ];
    }
}

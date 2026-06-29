<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class FaceVerificationService
{
    public function saveBase64Image(?string $base64Image, string $folder): ?string
    {
        if (!$base64Image || !str_contains($base64Image, 'base64,')) {
            return null;
        }

        [$meta, $content] = explode('base64,', $base64Image, 2);
        $binary = base64_decode($content, true);
        if ($binary === false) {
            return null;
        }

        $filename = $folder.'/'.date('YmdHis').'_'.uniqid().'.jpg';
        Storage::disk('public')->put($filename, $binary);
        return $filename;
    }

    public function verify(?string $base64Image): bool
    {
        // Placeholder hemat biaya: memastikan foto wajah/webcam terkirim.
        // Untuk face recognition sungguhan, panggil API Python/FastAPI di sini.
        return !empty($base64Image) && str_contains($base64Image, 'base64,');
    }
}

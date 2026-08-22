<?php

namespace App\Casts;

use App\Models\TeacherFaceEnrollment;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;
use JsonException;

class EncryptedFaceDescriptor implements CastsAttributes
{
    private const PREFIX = 'enc:';

    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        return self::decode($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : self::encode($value);
    }

    public static function isEncrypted(mixed $value): bool
    {
        return is_string($value) && str_starts_with($value, self::PREFIX);
    }

    public static function encode(mixed $value): string
    {
        $descriptor = self::normalize($value);

        try {
            $json = json_encode($descriptor, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Template wajah tidak dapat diserialisasi.', previous: $exception);
        }

        return self::PREFIX.Crypt::encryptString($json);
    }

    public static function decode(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return self::normalize($value);
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Format template wajah tidak valid.');
        }

        try {
            $json = self::isEncrypted($value)
                ? Crypt::decryptString(substr($value, strlen(self::PREFIX)))
                : $value;
            $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException $exception) {
            throw new InvalidArgumentException('Template wajah tidak dapat dibaca.', previous: $exception);
        }

        return self::normalize($decoded);
    }

    private static function normalize(mixed $value): array
    {
        if (! is_array($value) || count($value) !== TeacherFaceEnrollment::EMBEDDING_DIMENSIONS) {
            throw new InvalidArgumentException('Dimensi template wajah tidak sesuai model.');
        }

        $normalized = array_map(static function ($item): float {
            if (! is_numeric($item) || ! is_finite((float) $item)) {
                throw new InvalidArgumentException('Template wajah mengandung nilai tidak valid.');
            }

            return (float) $item;
        }, array_values($value));

        return $normalized;
    }
}

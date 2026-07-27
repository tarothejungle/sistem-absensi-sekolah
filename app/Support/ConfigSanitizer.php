<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Menjaga nilai konfigurasi rawan salah-tulis (mis. saat menyalin ke .env
 * produksi) tetap dalam bentuk yang valid, sehingga kesalahan format tidak
 * membekukan sesi/CSRF di seluruh aplikasi.
 */
final class ConfigSanitizer
{
    /**
     * Ubah nilai SESSION_DOMAIN menjadi host polos yang valid.
     *
     * Menerima input keliru seperti "https://contoh.sch.id/" atau
     * "contoh.sch.id:443/path" dan mengembalikan "contoh.sch.id".
     * Mengembalikan null bila kosong/tidak valid (localhost, IP, tanpa titik)
     * agar cookie sesi jatuh ke mode host-only yang aman — bukan ditolak
     * browser karena atribut Domain rusak.
     */
    public static function sessionDomain(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || strtolower($value) === 'null') {
            return null;
        }

        // Buang skema http(s):// bila ikut tertulis.
        if (str_contains($value, '://')) {
            $host = parse_url($value, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                $value = $host;
            }
        }

        // Sisakan host: buang path, query, dan port.
        $value = trim(explode(':', explode('?', explode('/', $value)[0])[0])[0]);

        if ($value === '') {
            return null;
        }

        // Leading dot (mis. ".contoh.sch.id") valid sebagai cookie domain.
        $candidate = ltrim($value, '.');

        if ($candidate === '' || ! preg_match('/^[A-Za-z0-9.-]+$/', $candidate)) {
            return null;
        }

        // localhost & IP address: pakai host-only (null) agar cookie tidak ditolak.
        if (strtolower($candidate) === 'localhost' || filter_var($candidate, FILTER_VALIDATE_IP)) {
            return null;
        }

        // Cookie domain harus ber-TLD (mengandung minimal satu titik).
        if (! str_contains($candidate, '.')) {
            return null;
        }

        return $value;
    }

    /**
     * Rapikan URL/origin: buang spasi & trailing slash.
     * Mengembalikan null bila kosong.
     */
    public static function trimUrl(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return rtrim($value, '/');
    }

    /**
     * Rapikan daftar origin CORS (buang trailing slash & entri kosong).
     *
     * @param  array<int, string>|string|null  $origins
     * @return array<int, string>
     */
    public static function corsOrigins(array|string|null $origins): array
    {
        if (is_string($origins)) {
            $origins = explode(',', $origins);
        }

        return array_values(array_filter(array_map(
            static fn ($origin) => self::trimUrl(is_string($origin) ? $origin : null),
            (array) $origins
        )));
    }
}

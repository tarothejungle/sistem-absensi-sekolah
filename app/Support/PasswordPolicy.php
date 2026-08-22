<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * Satu sumber kebenaran kebijakan password.
 *
 * Sebelumnya kebijakan terpecah tiga: AdminController memakai 8 karakter
 * plus kompleksitas, CreateSuperAdmin memakai 12 plus kompleksitas, sementara
 * ganti password dan reset password hanya `min:8` tanpa syarat kompleksitas
 * sehingga menjadi jalur penurunan kebijakan.
 */
class PasswordPolicy
{
    public static function rule(): Password
    {
        return Password::min(8)->mixedCase()->letters()->numbers()->symbols();
    }

    public static function superAdminRule(): Password
    {
        return Password::min(12)->mixedCase()->letters()->numbers()->symbols();
    }

    /**
     * Pesan kesalahan seragam untuk seluruh form password.
     *
     * @return array<string, string>
     */
    public static function messages(string $field = 'password'): array
    {
        return [
            $field.'.required' => 'Password wajib diisi.',
            $field.'.confirmed' => 'Konfirmasi password tidak sesuai.',
            $field.'.min' => 'Password minimal 8 karakter.',
        ];
    }
}

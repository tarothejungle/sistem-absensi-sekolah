<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\User;

class UserProfileCompletenessService
{
    private const REQUIRED_FIELDS = [
        'nama_lengkap' => ['label' => 'Nama Lengkap', 'editable_by_user' => true],
        'profile_photo' => ['label' => 'Foto Profil', 'editable_by_user' => true],
        'instansi_mengajar' => ['label' => 'Instansi Mengajar', 'editable_by_user' => true],
        'tempat_lahir' => ['label' => 'Tempat Lahir', 'editable_by_user' => true],
        'tanggal_lahir' => ['label' => 'Tanggal Lahir', 'editable_by_user' => true],
        'pendidikan_terakhir' => ['label' => 'Pendidikan Terakhir', 'editable_by_user' => true],
        'jenis_kelamin' => ['label' => 'Jenis Kelamin', 'editable_by_user' => false],
        'no_hp' => ['label' => 'No HP', 'editable_by_user' => false],
        'email' => ['label' => 'Email', 'editable_by_user' => false],
        'jabatan' => ['label' => 'Jabatan', 'editable_by_user' => false],
    ];

    private const ROLE_SPECIFIC_FIELDS = [
        'guru' => [
            'mata_pelajaran' => ['label' => 'Mata Pelajaran', 'editable_by_user' => false],
        ],
    ];

    public function check(User $user): array
    {
        $teacher = $user->teacher;
        $fields = array_merge(
            self::REQUIRED_FIELDS,
            self::ROLE_SPECIFIC_FIELDS[$user->role] ?? []
        );
        $missing = [];
        $provided = [];

        foreach ($fields as $field => $metadata) {
            if ($this->isFieldEmpty($user, $teacher, $field)) {
                $missing[$field] = $metadata;
            } else {
                $provided[$field] = $metadata;
            }
        }

        $totalFields = count($fields);
        $filledFields = count($provided);

        return [
            'is_complete' => $missing === [],
            'missing' => $missing,
            'provided' => $provided,
            'filled_count' => $filledFields,
            'total_count' => $totalFields,
            'percentage' => $totalFields > 0 ? (int) round(($filledFields / $totalFields) * 100) : 0,
        ];
    }

    public function isComplete(User $user): bool
    {
        return $this->check($user)['is_complete'];
    }

    public function missingFields(User $user): array
    {
        return $this->check($user)['missing'];
    }

    private function isFieldEmpty(User $user, ?Teacher $teacher, string $field): bool
    {
        $teacherFields = ['nama_lengkap', 'jenis_kelamin', 'no_hp', 'jabatan', 'mata_pelajaran'];
        $value = in_array($field, $teacherFields, true)
            ? $teacher?->{$field}
            : $user->{$field};

        return $value === null || (is_string($value) && trim($value) === '');
    }
}

<?php

namespace Database\Seeders;

use App\Models\SchoolLocation;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = $this->createUserWithRole('ADM001', 'super_admin');
        $kepalaSekolah = $this->createUserWithRole('KS001', 'kepala_sekolah');
        $guru = $this->createUserWithRole('GURU001', 'guru');

        Teacher::create([
            'user_id' => $admin->id,
            'nama_lengkap' => 'Admin BK',
            'jabatan' => 'Admin/BK',
        ]);

        Teacher::create([
            'user_id' => $kepalaSekolah->id,
            'nama_lengkap' => 'Kepala Sekolah',
            'jabatan' => 'Kepala Sekolah',
        ]);

        $teacher = Teacher::create([
            'user_id' => $guru->id,
            'nama_lengkap' => 'Budi Santoso',
            'jenis_kelamin' => 'L',
            'no_hp' => '08123456789',
            'email' => 'guru@example.com',
            'jabatan' => 'Guru Mapel',
            'mata_pelajaran' => 'Matematika',
        ]);

        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat'] as $hari) {
            WorkSchedule::create([
                'teacher_id' => $teacher->id,
                'hari' => $hari,
                'jam_masuk' => '07:00:00',
                'jam_pulang' => '15:00:00',
                'toleransi_terlambat' => 15,
            ]);
        }

        SchoolLocation::create([
            'nama_lokasi' => 'Sekolah Utama',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius_meter' => 150,
        ]);

        $this->call([
            AttendanceSessionSeeder::class,
        ]);
    }

    /**
     * role tidak fillable (anti privilege escalation) sehingga di-set eksplisit.
     */
    private function createUserWithRole(string $nip, string $role): User
    {
        $user = new User([
            'nip' => $nip,
            'password' => Hash::make('password'),
        ]);
        $user->role = $role;
        $user->status = 'aktif';
        $user->save();

        return $user;
    }
}

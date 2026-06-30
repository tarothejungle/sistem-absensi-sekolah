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
        $admin = User::create([
            'nip' => 'ADM001',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
        ]);

        $kepalaSekolah = User::create([
            'nip' => 'KS001',
            'password' => Hash::make('password'),
            'role' => 'kepala_sekolah',
        ]);

        $guru = User::create([
            'nip' => 'GURU001',
            'password' => Hash::make('password'),
            'role' => 'guru',
        ]);

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
}

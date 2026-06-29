<?php

namespace Database\Seeders;

use App\Models\AttendanceSession;
use Illuminate\Database\Seeder;

class AttendanceSessionSeeder extends Seeder
{
    public function run(): void
    {
        AttendanceSession::updateOrCreate(
            ['nama_sesi' => 'Sesi Pagi'],
            [
                'jam_masuk' => '07:00:00',
                'jam_pulang' => '12:00:00',
                'toleransi_terlambat' => 15,
                'batas_check_in_mulai' => '06:30:00',
                'batas_check_in_selesai' => '07:30:00',
                'batas_check_out_mulai' => '12:00:00',
                'batas_check_out_selesai' => '13:00:00',
                'status' => 'aktif',
            ]
        );

        AttendanceSession::updateOrCreate(
            ['nama_sesi' => 'Sesi Siang'],
            [
                'jam_masuk' => '12:30:00',
                'jam_pulang' => '17:00:00',
                'toleransi_terlambat' => 15,
                'batas_check_in_mulai' => '12:00:00',
                'batas_check_in_selesai' => '13:00:00',
                'batas_check_out_mulai' => '17:00:00',
                'batas_check_out_selesai' => '18:00:00',
                'status' => 'aktif',
            ]
        );
    }
}
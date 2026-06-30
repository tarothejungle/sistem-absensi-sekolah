<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\SchoolLocation;
use App\Models\Teacher;
use App\Models\User;
use App\Services\DailyAttendanceStatusService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceStatusTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_check_in_rejects_low_gps_accuracy(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'));

        [$teacher, $user] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        SchoolLocation::create([
            'nama_lokasi' => 'Sekolah Test',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius_meter' => 150,
            'status' => 'aktif',
        ]);

        $response = $this
            ->actingAs($user)
            ->post(route('attendance.checkin'), [
                'attendance_session_id' => $session->id,
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 250,
                'face_image' => 'data:image/jpeg;base64,' . base64_encode('fake-image'),
            ]);

        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('attendances', [
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2026-06-30',
        ]);
    }

    public function test_sync_marks_checked_in_without_checkout_as_incomplete_after_checkout_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 18:30:00', 'Asia/Jakarta'));

        [$teacher] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        $attendance = Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2026-06-30',
            'check_in_time' => Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'),
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        app(DailyAttendanceStatusService::class)->syncForDate('2026-06-30');

        $attendance->refresh();

        $this->assertSame('hadir_tidak_lengkap', $attendance->status_kehadiran);
        $this->assertNull($attendance->check_out_time);
    }

    public function test_sync_keeps_checked_in_without_checkout_open_before_checkout_cutoff(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-30 12:30:00', 'Asia/Jakarta'));

        [$teacher] = $this->createTeacherUser();
        $session = $this->createSession();
        $teacher->attendanceSessions()->attach($session->id);

        $attendance = Attendance::create([
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'tanggal' => '2026-06-30',
            'check_in_time' => Carbon::parse('2026-06-30 07:00:00', 'Asia/Jakarta'),
            'verification_method' => 'face',
            'status_kehadiran' => 'hadir',
            'keterlambatan_menit' => 0,
        ]);

        app(DailyAttendanceStatusService::class)->syncForDate('2026-06-30');

        $attendance->refresh();

        $this->assertSame('hadir', $attendance->status_kehadiran);
        $this->assertNull($attendance->check_out_time);
    }

    private function createSession(): AttendanceSession
    {
        return AttendanceSession::create([
            'nama_sesi' => 'Sesi Test ' . uniqid(),
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '17:00:00',
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => '06:30:00',
            'batas_check_in_selesai' => '08:00:00',
            'batas_check_out_mulai' => '12:00:00',
            'batas_check_out_selesai' => '18:00:00',
            'status' => 'aktif',
        ]);
    }

    private function createTeacherUser(): array
    {
        $user = User::create([
            'nip' => uniqid('nip_', true),
            'name' => 'Guru Absensi Test',
            'email' => uniqid('attendance_', true) . '@example.test',
            'password' => Hash::make('password'),
            'role' => 'guru',
            'status' => 'aktif',
        ]);

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'Guru Absensi Test ' . uniqid(),
            'email' => uniqid('teacher_', true) . '@example.test',
        ]);

        return [$teacher, $user];
    }
}

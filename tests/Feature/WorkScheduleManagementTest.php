<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\DutySchedule;
use App\Models\HolidaySetting;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\DailyAttendanceStatusService;
use App\Services\WorkScheduleResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WorkScheduleManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_super_admin_can_assign_different_and_multiple_sessions_per_day(): void
    {
        $admin = $this->createUser('super_admin');
        $teacher = $this->createTeacher('guru');
        $morning = $this->createSession('Pagi', '07:00:00', '12:00:00');
        $afternoon = $this->createSession('Siang', '13:00:00', '17:00:00');

        $this->actingAs($admin)->put(route('admin.work-schedules.update', $teacher), [
            'assignments' => [
                'senin' => [$morning->id],
                'selasa' => [$afternoon->id],
                'rabu' => [$morning->id, $afternoon->id],
            ],
        ])->assertRedirect(route('admin.work-schedules.index', ['teacher_id' => $teacher->id]));

        $resolver = app(WorkScheduleResolver::class);
        $this->assertSame([$morning->id], $resolver->sessionsForDate($teacher, '2026-08-03')->pluck('id')->all());
        $this->assertSame([$afternoon->id], $resolver->sessionsForDate($teacher, '2026-08-04')->pluck('id')->all());
        $this->assertSame([$morning->id, $afternoon->id], $resolver->sessionsForDate($teacher, '2026-08-05')->pluck('id')->all());

        $this->assertDatabaseHas('work_schedules', [
            'teacher_id' => $teacher->id,
            'hari' => 'selasa',
            'attendance_session_id' => $afternoon->id,
            'jam_masuk' => '13:00:00',
        ]);
    }

    public function test_regular_teacher_cannot_manage_work_schedules(): void
    {
        $user = $this->createUser('guru');

        $this->actingAs($user)->get(route('admin.work-schedules.index'))->assertForbidden();
    }

    public function test_super_admin_profile_is_excluded_and_cannot_receive_work_schedule(): void
    {
        $admin = $this->createUser('super_admin');
        $adminTeacher = Teacher::create([
            'user_id' => $admin->id,
            'nama_lengkap' => 'Profil Super Admin Jadwal Test',
        ]);
        $session = $this->createSession('Sesi Admin Terlarang', '07:00:00', '12:00:00');

        $this->actingAs($admin)
            ->get(route('admin.work-schedules.index'))
            ->assertOk()
            ->assertDontSee('Profil Super Admin Jadwal Test');

        $this->actingAs($admin)
            ->put(route('admin.work-schedules.update', $adminTeacher), [
                'assignments' => ['senin' => [$session->id]],
            ])
            ->assertNotFound();

        $this->assertFalse(WorkSchedule::where('teacher_id', $adminTeacher->id)->exists());
    }

    public function test_identity_update_does_not_change_existing_schedule(): void
    {
        $admin = $this->createUser('super_admin');
        $teacher = $this->createTeacher('guru');
        $session = $this->createSession('Jadwal Existing', '07:00:00', '12:00:00');

        $this->actingAs($admin)->put(route('admin.work-schedules.update', $teacher), [
            'assignments' => ['senin' => [$session->id]],
        ]);

        $this->actingAs($admin)->put(route('admin.teachers.update', $teacher), [
            'username' => $teacher->user->username,
            'role' => 'guru',
            'nama_lengkap' => 'Nama Identitas Baru',
            'jenis_kelamin' => 'L',
            'email' => $teacher->user->email,
            'jabatan' => 'Guru Kelas',
            'attendance_session_ids' => [],
            'attendance_days' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('work_schedules', [
            'teacher_id' => $teacher->id,
            'attendance_session_id' => $session->id,
            'hari' => 'senin',
            'status' => 'aktif',
        ]);
        $this->assertTrue($teacher->fresh()->attendanceSessions->contains($session));
    }

    public function test_work_schedule_keeps_primary_session_when_still_assigned(): void
    {
        $admin = $this->createUser('super_admin');
        $teacher = $this->createTeacher('guru');
        $morning = $this->createSession('Primary Pagi', '07:00:00', '12:00:00');
        $afternoon = $this->createSession('Secondary Siang', '13:00:00', '17:00:00');
        $teacher->attendanceSessions()->attach($afternoon->id, ['is_primary' => true]);

        $this->actingAs($admin)->put(route('admin.work-schedules.update', $teacher), [
            'assignments' => ['senin' => [$morning->id, $afternoon->id]],
        ])->assertRedirect();

        $primary = $teacher->fresh()->attendanceSessions->firstWhere('pivot.is_primary', true);
        $this->assertSame($afternoon->id, $primary?->id);
    }

    public function test_teacher_without_schedule_sees_configuration_message_and_cannot_be_marked_alfa(): void
    {
        $teacher = $this->createTeacher('guru');

        $this->actingAs($teacher->user)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertSee('Jadwal kerja belum diatur. Hubungi admin sekolah.');

        app(DailyAttendanceStatusService::class)
            ->syncTeacherForDate($teacher, now(config('app.timezone'))->startOfDay());

        $this->assertFalse(Attendance::where('teacher_id', $teacher->id)
            ->where('status_kehadiran', 'alfa')
            ->exists());
    }

    public function test_holiday_duty_uses_only_selected_sessions(): void
    {
        $teacher = $this->createTeacher('guru');
        $morning = $this->createSession('Pagi Piket', '07:00:00', '12:00:00');
        $afternoon = $this->createSession('Siang Piket', '13:00:00', '17:00:00');
        $holiday = HolidaySetting::create([
            'tanggal' => '2026-08-17',
            'nama_libur' => 'Hari Libur Test',
            'status' => 'aktif',
        ]);
        $duty = DutySchedule::create([
            'tanggal' => $holiday->tanggal,
            'nama_piket' => 'Piket Test',
            'status' => 'aktif',
        ]);
        $duty->teachers()->attach($teacher->id);
        $duty->attendanceSessions()->attach($afternoon->id);

        $sessions = app(WorkScheduleResolver::class)->sessionsForDate($teacher, '2026-08-17');

        $this->assertSame([$afternoon->id], $sessions->pluck('id')->all());
        $this->assertFalse($sessions->contains('id', $morning->id));
    }

    public function test_super_admin_can_store_duty_schedule_with_selected_session(): void
    {
        $admin = $this->createUser('super_admin');
        $teacher = $this->createTeacher('guru');
        $session = $this->createSession('Sesi Piket Simpan', '08:00:00', '12:00:00');

        $this->actingAs($admin)->post(route('admin.duty-schedules.store'), [
            'tanggal' => '2026-08-18',
            'nama_piket' => 'Piket Simpan Test',
            'status' => 'aktif',
            'teacher_ids' => [$teacher->id],
            'attendance_session_ids' => [$session->id],
        ])->assertRedirect();

        $dutySchedule = DutySchedule::where('nama_piket', 'Piket Simpan Test')->firstOrFail();
        $this->assertTrue($dutySchedule->teachers->contains($teacher));
        $this->assertTrue($dutySchedule->attendanceSessions->contains($session));
    }

    public function test_super_admin_profile_cannot_be_selected_as_duty_teacher(): void
    {
        $admin = $this->createUser('super_admin');
        $adminTeacher = Teacher::create([
            'user_id' => $admin->id,
            'nama_lengkap' => 'Admin Piket Terlarang',
        ]);
        $session = $this->createSession('Sesi Piket Validasi', '08:00:00', '12:00:00');

        $this->actingAs($admin)->post(route('admin.duty-schedules.store'), [
            'tanggal' => '2026-08-19',
            'nama_piket' => 'Piket Admin Terlarang',
            'status' => 'aktif',
            'teacher_ids' => [$adminTeacher->id],
            'attendance_session_ids' => [$session->id],
        ])->assertSessionHasErrors('teacher_ids.0');

        $this->assertDatabaseMissing('duty_schedules', ['nama_piket' => 'Piket Admin Terlarang']);
    }

    private function createSession(string $name, string $start, string $end): AttendanceSession
    {
        return AttendanceSession::create([
            'nama_sesi' => $name.' '.uniqid(),
            'jam_masuk' => $start,
            'jam_pulang' => $end,
            'toleransi_terlambat' => 15,
            'batas_check_in_mulai' => $start,
            'batas_check_in_selesai' => $end,
            'batas_check_out_mulai' => $start,
            'batas_check_out_selesai' => $end,
            'status' => 'aktif',
        ]);
    }

    private function createTeacher(string $role): Teacher
    {
        $user = $this->createUser($role);

        return Teacher::create([
            'user_id' => $user->id,
            'nama_lengkap' => 'PTK Jadwal '.uniqid(),
        ]);
    }

    private function createUser(string $role): User
    {
        $user = User::create([
            'username' => uniqid('jadwal_', true),
            'name' => 'User Jadwal '.uniqid(),
            'email' => uniqid('jadwal_', true).'@example.test',
            'password' => Hash::make('password'),
        ]);
        $user->forceFill(['role' => $role, 'status' => 'aktif'])->save();

        return $user;
    }
}

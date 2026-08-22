<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkScheduleController extends Controller
{
    private const DAYS = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];

    public function index(Request $request)
    {
        $teachers = Teacher::with(['user', 'schedules.attendanceSession'])
            ->whereHas('user', fn ($query) => $query
                ->where('status', 'aktif')
                ->whereIn('role', User::ATTENDANCE_ROLES))
            ->orderBy('nama_lengkap')
            ->get();
        $sessions = AttendanceSession::where('status', 'aktif')->orderBy('jam_masuk')->get();
        $selectedTeacher = $teachers->firstWhere('id', (int) $request->integer('teacher_id'))
            ?? $teachers->first();

        return view('admin.work-schedules.index', compact('teachers', 'sessions', 'selectedTeacher'));
    }

    public function update(Request $request, Teacher $teacher)
    {
        abort_unless($teacher->user?->hasAttendanceAccess(), 404);

        $data = $request->validate([
            'assignments' => ['nullable', 'array'],
            'assignments.*' => ['array'],
            'assignments.*.*' => [
                'integer',
                Rule::exists('attendance_sessions', 'id')->where('status', 'aktif'),
            ],
        ]);

        $assignments = collect($data['assignments'] ?? [])
            ->only(self::DAYS)
            ->map(fn ($sessionIds) => collect($sessionIds)->map(fn ($id) => (int) $id)->unique()->values());
        $sessions = AttendanceSession::whereIn('id', $assignments->flatten()->all())->get()->keyBy('id');

        DB::transaction(function () use ($teacher, $assignments, $sessions): void {
            $assignedSessionIds = $assignments->flatten()->unique()->values();
            $currentPrimaryId = $teacher->attendanceSessions()
                ->wherePivot('is_primary', true)
                ->value('attendance_sessions.id');
            $primarySessionId = $assignedSessionIds->contains($currentPrimaryId)
                ? (int) $currentPrimaryId
                : $assignedSessionIds
                    ->sortBy(fn ($sessionId) => $sessions->get($sessionId)?->jam_masuk)
                    ->first();

            WorkSchedule::where('teacher_id', $teacher->id)->delete();

            foreach ($assignments as $day => $sessionIds) {
                foreach ($sessionIds as $sessionId) {
                    $session = $sessions->get($sessionId);

                    WorkSchedule::create([
                        'teacher_id' => $teacher->id,
                        'attendance_session_id' => $session->id,
                        'hari' => $day,
                        'jam_masuk' => $session->jam_masuk,
                        'jam_pulang' => $session->jam_pulang,
                        'toleransi_terlambat' => $session->toleransi_terlambat,
                        'status' => 'aktif',
                    ]);
                }
            }

            $teacher->attendanceSessions()->sync(
                $assignedSessionIds->mapWithKeys(fn ($sessionId) => [
                    $sessionId => ['is_primary' => (int) $sessionId === (int) $primarySessionId],
                ])->all()
            );
        });

        return redirect()->route('admin.work-schedules.index', ['teacher_id' => $teacher->id])
            ->with('success', 'Jadwal kerja berhasil diperbarui.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\DutySchedule;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DutyScheduleController extends Controller
{
    public function index()
    {
        $dutySchedules = DutySchedule::with(['teachers.user', 'attendanceSessions'])
            ->orderByDesc('tanggal')
            ->paginate(10);

        $teachers = $this->activeTeachers();
        $sessions = AttendanceSession::where('status', 'aktif')->orderBy('jam_masuk')->get();

        return view('admin.duty-schedules.index', compact('dutySchedules', 'teachers', 'sessions'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $teacherIds = $data['teacher_ids'];
        $sessionIds = $data['attendance_session_ids'];
        unset($data['teacher_ids'], $data['attendance_session_ids']);

        $dutySchedule = DutySchedule::create($data);
        $dutySchedule->teachers()->sync($teacherIds);
        $dutySchedule->attendanceSessions()->sync($sessionIds);

        return back()->with('success', 'Jadwal piket berhasil ditambahkan.');
    }

    public function update(Request $request, DutySchedule $dutySchedule)
    {
        $data = $this->validatedData($request, $dutySchedule);
        $teacherIds = $data['teacher_ids'];
        $sessionIds = $data['attendance_session_ids'];
        unset($data['teacher_ids'], $data['attendance_session_ids']);

        $dutySchedule->update($data);
        $dutySchedule->teachers()->sync($teacherIds);
        $dutySchedule->attendanceSessions()->sync($sessionIds);

        return back()->with('success', 'Jadwal piket berhasil diperbarui.');
    }

    public function toggle(DutySchedule $dutySchedule)
    {
        $dutySchedule->update([
            'status' => $dutySchedule->status === 'aktif' ? 'nonaktif' : 'aktif',
        ]);

        return back()->with('success', 'Status jadwal piket berhasil diubah.');
    }

    public function destroy(DutySchedule $dutySchedule)
    {
        $dutySchedule->delete();

        return back()->with('success', 'Jadwal piket berhasil dihapus.');
    }

    private function validatedData(Request $request, ?DutySchedule $dutySchedule = null): array
    {
        $uniqueDateRule = Rule::unique('duty_schedules', 'tanggal');

        if ($dutySchedule) {
            $uniqueDateRule->ignore($dutySchedule->id);
        }

        return $request->validate([
            'tanggal' => [
                'required',
                'date',
                $uniqueDateRule,
            ],
            'nama_piket' => ['nullable', 'string', 'max:120'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'teacher_ids' => ['required', 'array', 'min:1'],
            'teacher_ids.*' => [
                'integer',
                'exists:teachers,id',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $hasAttendanceAccess = Teacher::whereKey($value)
                        ->whereHas('user', fn ($query) => $query
                            ->where('status', 'aktif')
                            ->whereIn('role', User::ATTENDANCE_ROLES))
                        ->exists();

                    if (! $hasAttendanceAccess) {
                        $fail('Petugas piket harus merupakan guru/karyawan aktif yang memiliki akses absensi.');
                    }
                },
            ],
            'attendance_session_ids' => ['required', 'array', 'min:1'],
            'attendance_session_ids.*' => [
                'integer',
                Rule::exists('attendance_sessions', 'id')->where('status', 'aktif'),
            ],
        ]);
    }

    private function activeTeachers()
    {
        return Teacher::with('user')
            ->whereHas('user', function ($query) {
                $query
                    ->where('status', 'aktif')
                    ->whereIn('role', User::ATTENDANCE_ROLES);
            })
            ->orderBy('nama_lengkap')
            ->get();
    }
}

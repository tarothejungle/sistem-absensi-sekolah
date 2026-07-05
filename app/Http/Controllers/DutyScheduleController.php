<?php

namespace App\Http\Controllers;

use App\Models\DutySchedule;
use App\Models\Teacher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DutyScheduleController extends Controller
{
    public function index()
    {
        $dutySchedules = DutySchedule::with(['teachers.user'])
            ->orderByDesc('tanggal')
            ->paginate(10);

        $teachers = $this->activeTeachers();

        return view('admin.duty-schedules.index', compact('dutySchedules', 'teachers'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $teacherIds = $data['teacher_ids'];
        unset($data['teacher_ids']);

        $dutySchedule = DutySchedule::create($data);
        $dutySchedule->teachers()->sync($teacherIds);

        return back()->with('success', 'Jadwal piket berhasil ditambahkan.');
    }

    public function update(Request $request, DutySchedule $dutySchedule)
    {
        $data = $this->validatedData($request, $dutySchedule);
        $teacherIds = $data['teacher_ids'];
        unset($data['teacher_ids']);

        $dutySchedule->update($data);
        $dutySchedule->teachers()->sync($teacherIds);

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
            'teacher_ids.*' => ['integer', 'exists:teachers,id'],
        ]);
    }

    private function activeTeachers()
    {
        return Teacher::with('user')
            ->whereHas('user', function ($query) {
                $query->where('status', 'aktif');
            })
            ->orderBy('nama_lengkap')
            ->get();
    }
}

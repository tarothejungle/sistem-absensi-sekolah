<?php

namespace App\Http\Controllers;

use App\Exports\TeacherAccountsExport;
use App\Models\AttendanceSession;
use App\Models\SchoolLocation;
use App\Models\Teacher;
use App\Models\User;
use App\Models\WorkSchedule;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;

class AdminController extends Controller
{
    private array $days = ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu'];

    public function teachers(Request $request)
    {
        $keyword = $request->keyword;
        // $perPage = $this->resolvePerPage($request);

        $teachers = Teacher::with(['user', 'attendanceSessions', 'schedules'])
            ->whereHas('user', function ($query) {
                $query->whereIn('role', ['guru']);
            })
            ->when($keyword, function ($query) use ($keyword) {
                $query->where('nama_lengkap', 'like', '%' . $keyword . '%');
            })
            ->orderBy('nama_lengkap')
            ->paginate(7)
            ->appends($request->query());

        return view('admin.teachers', compact('teachers', 'keyword'));
    }

    public function createTeacher()
    {
        $teacher = null;

        $sessions = AttendanceSession::where('status', 'aktif')
            ->orderBy('jam_masuk')
            ->get();

        return view('admin.teacher_form', compact('teacher', 'sessions'));
    }

    public function storeTeacher(Request $request)
    {
        $existingUser = User::where('nip', $request->nip)
            ->orWhere('email', $request->email)
            ->first();

        $data = $request->validate([
            'nip' => 'required|string|max:30',
            'attendance_session_ids' => 'required|array',
            'attendance_session_ids.*' => 'exists:attendance_sessions,id',
            'attendance_days' => 'required|array|min:1',
            'attendance_days.*' => 'in:' . implode(',', $this->days),
            'password' => $existingUser ? 'nullable|string|min:5' : 'required|string|min:6',
            'nama_lengkap' => 'required|string|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'no_hp' => 'nullable|string|max:20',
            'email' => 'required|email|max:150',
            'jabatan' => 'nullable|string|max:100',
            'mata_pelajaran' => 'nullable|string|max:100',
        ]);

        if ($existingUser) {
            $user = $existingUser;

            $teacherSudahAda = Teacher::where('user_id', $user->id)->first();

            if ($teacherSudahAda) {
                return back()
                    ->withInput()
                    ->with('error', 'Data guru untuk akun ini sudah tersedia.');
            }
        } else {
            $user = User::create([
                'nip' => $data['nip'],
                'name' => $data['nama_lengkap'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => 'guru',
                'status' => 'aktif',
            ]);
        }

        $teacher = Teacher::create([
            'user_id' => $user->id,
            'nip' => $data['nip'],
            'attendance_session_id' => $data['attendance_session_ids'][0] ?? null,
            'nama_lengkap' => $data['nama_lengkap'],
            'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
            'no_hp' => $data['no_hp'] ?? null,
            'email' => $data['email'],
            'jabatan' => $data['jabatan'] ?? null,
            'mata_pelajaran' => $data['mata_pelajaran'] ?? null,
        ]);

        $teacher->attendanceSessions()->sync($data['attendance_session_ids']);
        $this->syncTeacherWorkSchedules($teacher, $data['attendance_days']);

        return redirect()
            ->route('admin.teachers')
            ->with('success', 'Data guru berhasil ditambahkan.');
    }

    public function editTeacher(Teacher $teacher)
    {
        $teacher->load(['attendanceSessions', 'schedules']);

        $sessions = AttendanceSession::where('status', 'aktif')
            ->orderBy('jam_masuk')
            ->get();

        return view('admin.teacher_form', compact('teacher', 'sessions'));
    }

    public function updateTeacher(Request $request, Teacher $teacher)
    {
        $data = $request->validate([
            'nip' => 'required|string|max:30|unique:users,nip,' . $teacher->user_id,
            'attendance_session_ids' => 'required|array',
            'attendance_session_ids.*' => 'exists:attendance_sessions,id',
            'attendance_days' => 'required|array|min:1',
            'attendance_days.*' => 'in:' . implode(',', $this->days),
            'nama_lengkap' => 'required|string|max:100',
            'jenis_kelamin' => 'nullable|in:L,P',
            'no_hp' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email,' . $teacher->user_id,
            'jabatan' => 'nullable|string|max:100',
            'mata_pelajaran' => 'nullable|string|max:100',
        ]);

        $teacher->user->update([
            'nip' => $data['nip'],
            'name' => $data['nama_lengkap'],
            'email' => $data['email'],
        ]);

        $teacher->update([
            'attendance_session_id' => $data['attendance_session_ids'][0] ?? null,
            'nama_lengkap' => $data['nama_lengkap'],
            'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
            'no_hp' => $data['no_hp'] ?? null,
            'email' => $data['email'],
            'jabatan' => $data['jabatan'] ?? null,
            'mata_pelajaran' => $data['mata_pelajaran'] ?? null,
        ]);

        $teacher->attendanceSessions()->sync($data['attendance_session_ids']);
        $this->syncTeacherWorkSchedules($teacher, $data['attendance_days']);

        return redirect()
            ->route('admin.teachers')
            ->with('success', 'Data guru berhasil diperbarui.');
    }

    private function syncTeacherWorkSchedules(Teacher $teacher, array $days): void
    {
        $days = collect($days)
            ->filter(fn ($day) => in_array($day, $this->days, true))
            ->unique()
            ->values();

        WorkSchedule::where('teacher_id', $teacher->id)
            ->whereNotIn('hari', $days->all())
            ->update(['status' => 'nonaktif']);

        foreach ($days as $hari) {
            WorkSchedule::updateOrCreate(
                [
                    'teacher_id' => $teacher->id,
                    'hari' => $hari,
                ],
                [
                    'jam_masuk' => '07:00:00',
                    'jam_pulang' => '12:00:00',
                    'toleransi_terlambat' => 15,
                    'status' => 'aktif',
                ]
            );
        }
    }

    public function deleteTeacher(Teacher $teacher)
    {
        $teacher->user->delete();

        return back()->with('success', 'Data guru dihapus.');
    }

    public function editLocation()
    {
        $location = SchoolLocation::where('status', 'aktif')->first();

        return view('admin.location', compact('location'));
    }

    public function updateLocation(Request $request)
    {
        $data = $request->validate([
            'nama_lokasi' => 'required',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'radius_meter' => 'required|integer|min:20',
        ]);

        SchoolLocation::updateOrCreate(
            ['status' => 'aktif'],
            $data + ['status' => 'aktif']
        );

        return back()->with('success', 'Lokasi sekolah berhasil diperbarui.');
    }

    public function users(Request $request)
    {
        // $perPage = $this->resolvePerPage($request);

        $users = User::whereIn('role', ['super_admin', 'kepala_sekolah', 'bendahara'])
            ->orderBy('role', 'desc')
            ->paginate(7)
            ->appends($request->query());

        return view('admin.users', compact('users'));
    }

    public function createUser()
    {
        $user = null;

        return view('admin.user_form', compact('user'));
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'nip' => 'required|string|max:30|unique:users,nip',
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'role' => 'required|in:bendahara,kepala_sekolah,super_admin',
            'password' => 'required|string|min:5',
            'status' => 'required|in:aktif,nonaktif',
        ]);

        User::create([
            'nip' => $request->nip,
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'password' => Hash::make($request->password),
            'status' => $request->status,
        ]);

        return redirect()
            ->route('admin.users')
            ->with('success', 'Data pengguna berhasil ditambahkan.');
    }

    public function editUser(User $user)
    {
        return view('admin.user_form', compact('user'));
    }

    public function updateUser(Request $request, User $user)
    {
        $request->validate([
            'nip' => 'required|string|max:30|unique:users,nip,' . $user->id,
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:bendahara,kepala_sekolah,super_admin',
            'password' => 'nullable|string|min:5',
            'status' => 'required|in:aktif,nonaktif',
        ]);

        $data = [
            'nip' => $request->nip,
            'name' => $request->name,
            'email' => $request->email,
            'role' => $request->role,
            'status' => $request->status,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users')
            ->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function deleteUser(User $user)
    {
        if (auth()->id() === $user->id) {
            return back()->with('error', 'Akun yang sedang digunakan tidak boleh dihapus.');
        }

        $user->delete();

        return back()->with('success', 'Data pengguna berhasil dihapus.');
    }

    public function toggleUserStatus(User $user)
    {
        if (auth()->id() === $user->id && $user->status === 'aktif') {
            return back()->with('error', 'Akun yang sedang digunakan tidak boleh dinonaktifkan.');
        }

        $user->update([
            'status' => $user->status === 'aktif' ? 'nonaktif' : 'aktif',
        ]);

        return back()->with('success', 'Status pengguna berhasil diubah.');
    }

    public function exportTeacherAccountsExcel()
    {
        return Excel::download(
            new TeacherAccountsExport,
            'data-akun-login-guru.xlsx'
        );
    }

    public function exportTeacherAccountsPdf()
    {
        $teachers = Teacher::with(['user', 'attendanceSessions', 'schedules'])
            ->whereHas('user', function ($query) {
                $query->where('role', 'guru');
            })
            ->orderBy('nama_lengkap')
            ->get();

        $pdf = Pdf::loadView('admin.exports.teacher_accounts_pdf', compact('teachers'))
            ->setPaper('a4', 'landscape');

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="data-akun-login-guru.pdf"',
        ]);
    }


    private function resolvePerPage(Request $request, int $default = 7): int
    {
        $allowed = [7, 14, 21, 28, 35, 70];
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }
}

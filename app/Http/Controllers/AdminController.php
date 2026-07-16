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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
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
                $query->whereIn('role', Teacher::DATA_GURU_ROLES);
            })
            ->when($keyword, function ($query) use ($keyword) {
                $query->where('nama_lengkap', 'like', '%'.$keyword.'%');
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
        $data = $request->validate([
            'nip' => ['required', 'string', 'max:30', 'unique:users,nip'],
            'role' => ['required', Rule::in(Teacher::DATA_GURU_ROLES)],
            'attendance_session_ids' => 'required|array',
            'attendance_session_ids.*' => 'exists:attendance_sessions,id',
            'attendance_days' => 'required|array|min:1',
            'attendance_days.*' => 'in:'.implode(',', $this->days),
            'password' => ['required', 'string', $this->strongPasswordRule()],
            'nama_lengkap' => 'required|string|max:100',
            'jenis_kelamin' => 'required|in:L,P',
            'no_hp' => 'nullable|string|max:20',
            'email' => 'required|email|max:150|unique:users,email',
            'jabatan' => 'required|in:Kepala Sekolah,Bendahara,Operator, Guru Kelas,Guru Bidang',
            'mata_pelajaran' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($data): void {
            $user = new User([
                'nip' => $data['nip'],
                'name' => $data['nama_lengkap'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->role = $data['role'];
            $user->status = 'aktif';
            $user->save();

            $teacher = Teacher::create([
                'user_id' => $user->id,
                'nama_lengkap' => $data['nama_lengkap'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'no_hp' => $data['no_hp'] ?? null,
                'jabatan' => $data['jabatan'],
                'mata_pelajaran' => $data['mata_pelajaran'] ?? null,
            ]);

            $teacher->attendanceSessions()->sync($data['attendance_session_ids']);
            $this->syncTeacherWorkSchedules($teacher, $data['attendance_days']);
        });

        return redirect()
            ->route('admin.teachers', $request->only(['page', 'keyword']))
            ->with('success', 'Data guru berhasil ditambahkan.');
    }

    public function editTeacher(Teacher $teacher)
    {
        abort_unless(in_array($teacher->user?->role, Teacher::DATA_GURU_ROLES, true), 404);

        $teacher->load(['attendanceSessions', 'schedules']);

        $sessions = AttendanceSession::where('status', 'aktif')
            ->orderBy('jam_masuk')
            ->get();

        return view('admin.teacher_form', compact('teacher', 'sessions'));
    }

    public function updateTeacher(Request $request, Teacher $teacher)
    {
        abort_unless(in_array($teacher->user?->role, Teacher::DATA_GURU_ROLES, true), 404);

        $data = $request->validate([
            'nip' => 'required|string|max:30|unique:users,nip,'.$teacher->user_id,
            'role' => ['required', Rule::in(Teacher::DATA_GURU_ROLES)],
            'attendance_session_ids' => 'required|array',
            'attendance_session_ids.*' => 'exists:attendance_sessions,id',
            'attendance_days' => 'required|array|min:1',
            'attendance_days.*' => 'in:'.implode(',', $this->days),
            'nama_lengkap' => 'required|string|max:100',
            'jenis_kelamin' => 'required|in:L,P',
            'no_hp' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email,'.$teacher->user_id,
            'jabatan' => 'required|in:Kepala Sekolah,Bendahara,Operator, Guru Kelas,Guru Bidang',
            'mata_pelajaran' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($data, $teacher): void {
            $teacher->user->update([
                'nip' => $data['nip'],
                'name' => $data['nama_lengkap'],
                'email' => $data['email'],
            ]);
            $teacher->user->role = $data['role'];
            $teacher->user->save();

            $teacher->update([
                'nama_lengkap' => $data['nama_lengkap'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'no_hp' => $data['no_hp'] ?? null,
                'jabatan' => $data['jabatan'],
                'mata_pelajaran' => $data['mata_pelajaran'] ?? null,
            ]);

            $teacher->attendanceSessions()->sync($data['attendance_session_ids']);
            $this->syncTeacherWorkSchedules($teacher, $data['attendance_days']);
        });

        return redirect()
            ->route('admin.teachers', $request->only(['page', 'keyword']))
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

    public function bulkDeleteTeachers(Request $request)
    {
        $data = $request->validate([
            'teacher_ids' => 'required|array|min:1',
            'teacher_ids.*' => 'exists:teachers,id',
        ]);

        $teachers = Teacher::with('user')
            ->whereIn('id', $data['teacher_ids'])
            ->get();

        if ($teachers->isEmpty()) {
            return back()->with('error', 'Pilih data guru yang ingin dihapus.');
        }

        $userIds = $teachers
            ->pluck('user_id')
            ->filter()
            ->all();

        User::whereIn('id', $userIds)->delete();

        return back()->with('success', 'Data guru terpilih berhasil dihapus.');
    }

    public function deleteTeacher(Teacher $teacher)
    {
        abort_unless(in_array($teacher->user?->role, Teacher::DATA_GURU_ROLES, true), 404);

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

        $users = User::where('role', 'super_admin')
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
            'password' => ['required', 'string', $this->strongPasswordRule()],
        ]);

        $user = new User([
            'nip' => $request->nip,
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        // role & status di-set eksplisit (tidak fillable). Sudah divalidasi dengan in:.
        $user->role = 'super_admin';
        $user->status = 'aktif';
        $user->save();

        return redirect()
            ->route('admin.users')
            ->with('success', 'Data admin berhasil ditambahkan.');
    }

    public function editUser(User $user)
    {
        abort_unless($user->role === 'super_admin', 404);

        return view('admin.user_form', compact('user'));
    }

    public function updateUser(Request $request, User $user)
    {
        abort_unless($user->role === 'super_admin', 404);

        $request->validate([
            'nip' => 'required|string|max:30|unique:users,nip,'.$user->id,
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => ['nullable', 'string', $this->strongPasswordRule()],
        ]);

        $data = [
            'nip' => $request->nip,
            'name' => $request->name,
            'email' => $request->email,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users')
            ->with('success', 'Data admin berhasil diperbarui.');
    }

    public function bulkDeleteUsers(Request $request)
    {
        $data = $request->validate([
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
        ]);

        $userIds = collect($data['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->reject(fn ($id) => $id === auth()->id())
            ->values();

        if ($userIds->isEmpty()) {
            return back()->with('error', 'Akun yang sedang digunakan tidak boleh dihapus.');
        }

        User::where('role', 'super_admin')->whereIn('id', $userIds)->delete();

        return back()->with('success', 'Data admin terpilih berhasil dihapus.');
    }

    public function deleteUser(User $user)
    {
        abort_unless($user->role === 'super_admin', 404);

        if (auth()->id() === $user->id) {
            return back()->with('error', 'Akun yang sedang digunakan tidak boleh dihapus.');
        }

        $user->delete();

        return back()->with('success', 'Data admin berhasil dihapus.');
    }

    public function toggleUserStatus(User $user)
    {
        abort_unless($user->role === 'super_admin', 404);

        if (auth()->id() === $user->id && $user->status === 'aktif') {
            return back()->with('error', 'Akun yang sedang digunakan tidak boleh dinonaktifkan.');
        }

        // status tidak fillable — set eksplisit.
        $user->status = $user->status === 'aktif' ? 'nonaktif' : 'aktif';
        $user->save();

        return back()->with('success', 'Status admin berhasil diubah.');
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
                $query->whereIn('role', Teacher::DATA_GURU_ROLES);
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

    private function strongPasswordRule(): Password
    {
        return Password::min(8)->mixedCase()->letters()->numbers()->symbols();
    }
}

<?php

namespace App\Http\Controllers;

use App\Exports\TeacherAccountsExport;
use App\Models\SchoolLocation;
use App\Models\Teacher;
use App\Models\User;
use App\Services\TeacherLifecycleService;
use App\Support\PasswordPolicy;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Maatwebsite\Excel\Facades\Excel;

class AdminController extends Controller
{
    public function teachers(Request $request)
    {
        $keyword = $request->keyword;

        $teachers = Teacher::with(['user', 'attendanceSessions', 'schedules', 'faceEnrollment'])
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

        return view('admin.teacher_form', compact('teacher'));
    }

    public function storeTeacher(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:30', 'unique:users,username'],
            'role' => ['required', Rule::in(Teacher::DATA_GURU_ROLES)],
            'password' => ['required', 'string', $this->strongPasswordRule()],
            'nama_lengkap' => 'required|string|max:100',
            'jenis_kelamin' => 'required|in:L,P',
            'no_hp' => 'nullable|string|max:20',
            'email' => 'required|email|max:150|unique:users,email',
            'jabatan' => ['required', Rule::in([
                'Kepala Sekolah',
                'Bendahara',
                'Operator',
                'Tenaga Kependidikan',
                'Guru Kelas',
                'Guru Bidang',
            ])],
            'mata_pelajaran' => 'nullable|string|max:100',
        ]);

        $teacher = DB::transaction(function () use ($data): Teacher {
            $user = new User([
                'username' => $data['username'],
                'name' => $data['nama_lengkap'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);
            $user->role = $data['role'];
            $user->status = 'aktif';
            $user->save();

            return Teacher::create([
                'user_id' => $user->id,
                'nama_lengkap' => $data['nama_lengkap'],
                'jenis_kelamin' => $data['jenis_kelamin'],
                'no_hp' => $data['no_hp'] ?? null,
                'jabatan' => $data['jabatan'],
                'mata_pelajaran' => $data['mata_pelajaran'] ?? null,
            ]);

        });

        return redirect()
            ->route('admin.work-schedules.index', ['teacher_id' => $teacher->id])
            ->with('success', 'Data guru berhasil ditambahkan. Atur jadwal kerja agar akun dapat melakukan absensi.');
    }

    public function editTeacher(Teacher $teacher)
    {
        abort_unless(in_array($teacher->user?->role, Teacher::DATA_GURU_ROLES, true), 404);

        $teacher->load('faceEnrollment');

        return view('admin.teacher_form', compact('teacher'));
    }

    public function updateTeacher(Request $request, Teacher $teacher)
    {
        abort_unless(in_array($teacher->user?->role, Teacher::DATA_GURU_ROLES, true), 404);

        $data = $request->validate([
            'username' => 'required|string|max:30|unique:users,username,'.$teacher->user_id,
            'role' => ['required', Rule::in(Teacher::DATA_GURU_ROLES)],
            'nama_lengkap' => 'required|string|max:100',
            'jenis_kelamin' => 'required|in:L,P',
            'no_hp' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email,'.$teacher->user_id,
            'jabatan' => ['required', Rule::in([
                'Kepala Sekolah',
                'Bendahara',
                'Operator',
                'Tenaga Kependidikan',
                'Guru Kelas',
                'Guru Bidang',
            ])],
            'mata_pelajaran' => 'nullable|string|max:100',
        ]);

        DB::transaction(function () use ($data, $teacher): void {
            $teacher->user->update([
                'username' => $data['username'],
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

        });

        return redirect()
            ->route('admin.teachers', $request->only(['page', 'keyword']))
            ->with('success', 'Data guru berhasil diperbarui.');
    }

    public function deleteFaceEnrollment(Request $request, Teacher $teacher)
    {
        abort_unless(in_array($teacher->user?->role, Teacher::DATA_GURU_ROLES, true), 404);

        $teacher->faceEnrollment()?->delete();

        return redirect()
            ->route('admin.teachers.edit', array_merge(['teacher' => $teacher], $request->only(['page', 'keyword'])))
            ->with('success', 'Data wajah '.$teacher->nama_lengkap.' berhasil dihapus.');
    }

    public function bulkDeleteTeachers(Request $request, TeacherLifecycleService $lifecycle)
    {
        $data = $request->validate([
            'teacher_ids' => 'required|array|min:1',
            'teacher_ids.*' => 'integer|distinct|exists:teachers,id',
        ]);

        $teachers = Teacher::with('user')
            ->whereIn('id', $data['teacher_ids'])
            ->whereHas('user', fn ($query) => $query->whereIn('role', Teacher::DATA_GURU_ROLES))
            ->get();

        if ($teachers->count() !== count($data['teacher_ids'])) {
            return back()->with('error', 'Hanya akun guru/PTK yang dapat diarsipkan.');
        }

        $archived = 0;
        $deleted = 0;

        foreach ($teachers as $teacher) {
            $result = $lifecycle->archiveOrDeleteTeacher($teacher);
            $result['action'] === 'archived' ? $archived++ : $deleted++;
        }

        return back()->with('success', "{$archived} akun diarsipkan dan {$deleted} akun tanpa histori dihapus.");
    }

    public function deleteTeacher(Teacher $teacher, TeacherLifecycleService $lifecycle)
    {
        abort_unless(in_array($teacher->user?->role, Teacher::DATA_GURU_ROLES, true), 404);

        $result = $lifecycle->archiveOrDeleteTeacher($teacher);
        $message = $result['action'] === 'archived'
            ? 'Data guru diarsipkan agar seluruh histori tetap tersimpan.'
            : 'Data guru tanpa histori berhasil dihapus.';

        return back()->with('success', $message);
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
            'max_gps_accuracy' => 'required|integer|min:20|max:1000',
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
            'username' => 'required|string|max:30|unique:users,username',
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', $this->strongPasswordRule()],
        ]);

        $user = new User([
            'username' => $request->username,
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
            'username' => 'required|string|max:30|unique:users,username,'.$user->id,
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => ['nullable', 'string', $this->strongPasswordRule()],
        ]);

        $data = [
            'username' => $request->username,
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

    public function bulkDeleteUsers(Request $request, TeacherLifecycleService $lifecycle)
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

        $users = User::where('role', 'super_admin')->whereIn('id', $userIds)->get();
        $archived = 0;
        $deleted = 0;

        foreach ($users as $user) {
            $result = $lifecycle->archiveOrDeleteUser($user);
            $result['action'] === 'archived' ? $archived++ : $deleted++;
        }

        return back()->with('success', "{$archived} akun admin diarsipkan dan {$deleted} akun tanpa histori dihapus.");
    }

    public function deleteUser(User $user, TeacherLifecycleService $lifecycle)
    {
        abort_unless($user->role === 'super_admin', 404);

        if (auth()->id() === $user->id) {
            return back()->with('error', 'Akun yang sedang digunakan tidak boleh dihapus.');
        }

        $result = $lifecycle->archiveOrDeleteUser($user);
        $message = $result['action'] === 'archived'
            ? 'Akun admin diarsipkan agar histori tetap tersimpan.'
            : 'Data admin tanpa histori berhasil dihapus.';

        return back()->with('success', $message);
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
        return PasswordPolicy::rule();
    }
}

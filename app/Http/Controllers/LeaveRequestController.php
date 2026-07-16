<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AppNotificationService;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        // $perPage = $this->resolvePerPage($request);

        $query = LeaveRequest::with(['teacher.user', 'infalTeacher.user', 'approver']);

        if (in_array($user->role, ['guru', 'bendahara'])) {
            $teacher = $user->teacher;

            if (!$teacher) {
                return redirect()
                    ->route('dashboard')
                    ->with('error', 'Data guru untuk akun ini belum tersedia.');
            }

            $query->where(function ($q) use ($teacher) {
                $q->where('teacher_id', $teacher->id)
                ->orWhere('infal_teacher_id', $teacher->id);
            });
        }

        $leaves = $query
            ->latest()
            ->paginate(10);
            // ->paginate($perPage)
            // ->withQueryString();

        return view('leave.index', compact('leaves'));
    }

    public function create()
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        if (!in_array($user->role, ['guru', 'bendahara'])) {
            abort(403);
        }

        if (!$teacher) {
            return redirect()
                ->route('leave.index')
                ->with('error', 'Data guru untuk akun ini belum tersedia.');
        }

        $teachers = Teacher::where('id', '!=', $teacher->id)
            ->orderBy('nama_lengkap')
            ->get();

        return view('leave.create', compact('teachers'));
    }

    public function edit(LeaveRequest $leave)
    {
        $user = auth()->user();

        if (in_array($user->role, ['guru', 'bendahara'])) {
            $teacher = $user->teacher;

            if (!$teacher || $leave->teacher_id != $teacher->id) {
                abort(403, 'Anda tidak memiliki akses ke pengajuan ini.');
            }
        }

        $bolehEditNormal = $leave->status_pengajuan === 'pending';

        $bolehGantiInfal = $leave->status_pengajuan === 'disetujui'
            && $leave->status_infal === 'ditolak';

        if (!$bolehEditNormal && !$bolehGantiInfal) {
            return redirect()
                ->route('leave.index')
                ->with('error', 'Pengajuan ini tidak dapat diedit.');
        }

        $teachers = Teacher::where('id', '!=', $leave->teacher_id)
            ->orderBy('nama_lengkap')
            ->get();

        return view('leave.edit', compact('leave', 'teachers'));
    }

    public function update(Request $request, LeaveRequest $leave)
    {
        $user = auth()->user();
    
        $modeGantiInfal = $leave->infal_teacher_id
            && $leave->status_pengajuan === 'disetujui'
            && $leave->status_infal === 'ditolak';
    
        $teacher = $user->teacher;
    
        $roleBebasAkses = in_array($user->role, ['super_admin', 'kepala_sekolah']);
    
        $pengajuanMilikUser = $teacher && (int) $leave->teacher_id === (int) $teacher->id;
    
        if (! $roleBebasAkses && ! $pengajuanMilikUser) {
            abort(403, 'Anda tidak memiliki akses ke pengajuan ini.');
        }
    
        if ($modeGantiInfal) {
            $request->validate([
                'infal_teacher_id' => 'nullable|exists:teachers,id',
            ]);

            $newInfalTeacherId = $request->filled('infal_teacher_id')
                ? $request->infal_teacher_id
                : null;

            if ($newInfalTeacherId && (int) $newInfalTeacherId === (int) $leave->teacher_id) {
                return back()
                    ->withInput()
                    ->with('error', 'Guru pengganti tidak boleh sama dengan guru yang mengajukan.');
            }

            $leave->update([
                'infal_teacher_id' => $newInfalTeacherId,
                'status_infal' => $newInfalTeacherId ? 'pending' : 'disetujui',
                'catatan_infal' => null,
            ]);

            return redirect()
                ->route('leave.index')
                ->with('success', $newInfalTeacherId
                    ? 'Guru pengganti berhasil diperbarui. Menunggu persetujuan guru pengganti.'
                    : 'Pengajuan diperbarui tanpa guru pengganti.'
                );
        }

        if ($leave->status_pengajuan !== 'pending') {
            return redirect()
                ->route('leave.index')
                ->with('error', 'Pengajuan ini tidak dapat diedit karena sudah diproses.');
        }
    
        $validated = $this->validateLeaveRequestPayload($request);

        if ($request->filled('infal_teacher_id') && (int) $request->infal_teacher_id === (int) $leave->teacher_id) {
            return back()
                ->withInput()
                ->with('error', 'Guru pengganti tidak boleh sama dengan guru yang mengajukan.');
        }
    
        $infalTeacherId = $validated['infal_teacher_id'];

        $data = [
            'jenis_pengajuan' => $validated['jenis_pengajuan'],
            'infal_teacher_id' => $infalTeacherId,
            'is_sementara' => $validated['is_sementara'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'jam_mulai' => $validated['jam_mulai'],
            'jam_selesai' => $validated['jam_selesai'],
            'alasan' => $validated['alasan'],
            'status_infal' => $infalTeacherId ? 'pending' : 'disetujui',
        ];
    
        if ($request->hasFile('lampiran')) {
            $this->deleteAttachmentFile($leave->lampiran);

            $data['lampiran'] = $this->storeAttachmentFile($request->file('lampiran'));
        }
    
        $leave->update($data);
    
        return redirect()
            ->route('leave.index')
            ->with('success', 'Pengajuan berhasil diperbarui.');
    }

    public function destroy(LeaveRequest $leave)
    {
        $user = auth()->user();

        if (in_array($user->role, ['guru', 'bendahara'])) {
            $teacher = $user->teacher;

            if (!$teacher || $leave->teacher_id !== $teacher->id) {
                abort(403, 'Anda tidak memiliki akses ke pengajuan ini.');
            }
        }

        if ($leave->status_pengajuan !== 'pending') {
            return redirect()
                ->route('leave.index')
                ->with('error', 'Pengajuan yang sudah diproses tidak dapat dihapus.');
        }

        if ($leave->lampiran) {
            $this->deleteAttachmentFile($leave->lampiran);
        }

        $leave->delete();

        return redirect()
            ->route('leave.index')
            ->with('success', 'Pengajuan berhasil dihapus.');
    }

    public function store(Request $request)
    {
        $validated = $this->validateLeaveRequestPayload($request);

        $user = auth()->user();
        $teacher = $user->teacher;

        if (!in_array($user->role, ['guru', 'bendahara'])) {
            abort(403);
        }

        if (!$teacher) {
            return back()->with('error', 'Data guru untuk akun ini belum tersedia.');
        }

        if ($request->filled('infal_teacher_id') && (int) $request->infal_teacher_id === (int) $teacher->id) {
            return back()
                ->withInput()
                ->with('error', 'Guru pengganti tidak boleh sama dengan guru yang mengajukan.');
        }

        $infalTeacherId = $validated['infal_teacher_id'];

        $data = [
            'teacher_id' => $teacher->id,
            'jenis_pengajuan' => $validated['jenis_pengajuan'],
            'infal_teacher_id' => $infalTeacherId,
            'is_sementara' => $validated['is_sementara'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'jam_mulai' => $validated['jam_mulai'],
            'jam_selesai' => $validated['jam_selesai'],
            'alasan' => $validated['alasan'],
            'status_pengajuan' => 'pending',
            'status_infal' => $infalTeacherId ? 'pending' : 'disetujui',
        ];

        if ($request->hasFile('lampiran')) {
            $data['lampiran'] = $this->storeAttachmentFile($request->file('lampiran'));
        }

        $leave = LeaveRequest::create($data);

        AppNotificationService::sendToRoles(
            ['super_admin', 'kepala_sekolah'],
            'Pengajuan izin/cuti baru',
            ($teacher->nama_lengkap ?? 'Guru') . ' mengajukan ' . ucfirst(str_replace('_', ' ', $leave->jenis_pengajuan)) . ' dan menunggu approval.',
            'leave',
            route('leave.index')
        );

        if ($leave->infalTeacher && $leave->infalTeacher->user_id) {
            AppNotificationService::send(
                $leave->infalTeacher->user_id,
                'Permintaan guru pengganti',
                ($teacher->nama_lengkap ?? 'Guru') . ' memilih Anda sebagai guru infal/pengganti.',
                'infal',
                route('leave.index')
            );
        }

        return redirect()
            ->route('leave.index')
            ->with('success', 'Pengajuan berhasil dikirim dan menunggu approval.');
    }

    public function approve(LeaveRequest $leave)
    {
        if (!in_array(auth()->user()->role, ['super_admin', 'kepala_sekolah'])) {
            abort(403);
        }

        if ($leave->status_pengajuan !== 'pending') {
            return back()->with('error', 'Pengajuan ini sudah diproses.');
        }

        $data = request()->validate([
            'catatan_approval' => 'nullable|string|max:500',
        ]);

        $leave->update([
            'status_pengajuan' => 'disetujui',
            'status_infal' => $leave->infal_teacher_id ? $leave->status_infal : 'disetujui',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'catatan_approval' => $data['catatan_approval'] ?? null,
        ]);

        if ($leave->teacher && $leave->teacher->user_id) {
            $message = 'Pengajuan ' . ucfirst(str_replace('_', ' ', $leave->jenis_pengajuan)) . ' Anda disetujui.';

            if (!empty($data['catatan_approval'])) {
                $message .= ' Catatan: ' . $data['catatan_approval'];
            }

            AppNotificationService::send(
                $leave->teacher->user_id,
                'Pengajuan izin/cuti disetujui',
                $message,
                'leave',
                route('leave.index')
            );
        }

        if ($leave->infal_teacher_id && $leave->infalTeacher && $leave->infalTeacher->user_id) {
            AppNotificationService::send(
                $leave->infalTeacher->user_id,
                'Permintaan menjadi guru pengganti',
                'Anda dipilih sebagai guru pengganti untuk ' . ($leave->teacher->nama_lengkap ?? 'guru') . '.',
                'infal',
                route('leave.index')
            );
        }

        return back()->with('success', 'Pengajuan berhasil disetujui.');
    }

    public function reject(LeaveRequest $leave)
    {
        if (!in_array(auth()->user()->role, ['super_admin', 'kepala_sekolah'])) {
            abort(403);
        }

        if ($leave->status_pengajuan !== 'pending') {
            return back()->with('error', 'Pengajuan ini sudah diproses.');
        }

        $data = request()->validate([
            'catatan_approval' => 'nullable|string|max:500',
        ]);

        $leave->update([
            'status_pengajuan' => 'ditolak',
            'status_infal' => 'ditolak',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'catatan_approval' => $data['catatan_approval'] ?? null,
        ]);

        if ($leave->teacher && $leave->teacher->user_id) {
            $message = 'Pengajuan ' . ucfirst(str_replace('_', ' ', $leave->jenis_pengajuan)) . ' Anda ditolak.';

            if (!empty($data['catatan_approval'])) {
                $message .= ' Catatan: ' . $data['catatan_approval'];
            }

            AppNotificationService::send(
                $leave->teacher->user_id,
                'Pengajuan izin/cuti ditolak',
                $message,
                'leave',
                route('leave.index')
            );
        }

        return back()->with('success', 'Pengajuan berhasil ditolak.');
    }

    public function approveInfal(LeaveRequest $leave)
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        if (!$teacher || $leave->infal_teacher_id != $teacher->id) {
            abort(403, 'Anda tidak memiliki akses untuk menyetujui penggantian ini.');
        }

        if ($leave->status_infal !== 'pending') {
            return back()->with('error', 'Status guru pengganti sudah diproses.');
        }

        $leave->update([
            'status_infal' => 'disetujui',
            'catatan_infal' => null,
        ]);

        if ($leave->teacher && $leave->teacher->user_id) {
            AppNotificationService::send(
                $leave->teacher->user_id,
                'Guru pengganti menyetujui',
                ($teacher->nama_lengkap ?? 'Guru pengganti') . ' menyetujui permintaan infal Anda.',
                'infal',
                route('leave.index')
            );
        }

        return back()->with('success', 'Anda menyetujui sebagai guru pengganti.');
    }

    public function rejectInfal(LeaveRequest $leave)
    {
        $user = auth()->user();
        $teacher = $user->teacher;

        if (!$teacher || $leave->infal_teacher_id != $teacher->id) {
            abort(403, 'Anda tidak memiliki akses untuk menolak penggantian ini.');
        }

        if ($leave->status_infal !== 'pending') {
            return back()->with('error', 'Status guru pengganti sudah diproses.');
        }

        $data = request()->validate([
            'catatan_infal' => 'nullable|string|max:500',
        ]);

        $leave->update([
            'status_infal' => 'ditolak',
            'catatan_infal' => $data['catatan_infal'] ?? null,
        ]);

        if ($leave->teacher && $leave->teacher->user_id) {
            $message = ($teacher->nama_lengkap ?? 'Guru pengganti') . ' menolak permintaan infal. Silakan ganti guru pengganti.';

            if (!empty($data['catatan_infal'])) {
                $message .= ' Catatan: ' . $data['catatan_infal'];
            }

            AppNotificationService::send(
                $leave->teacher->user_id,
                'Guru pengganti menolak',
                $message,
                'infal',
                route('leave.index')
            );
        }

        return back()->with('success', 'Anda menolak sebagai guru pengganti.');
    }

    public function showAttachment(LeaveRequest $leave)
    {
        $user = auth()->user();

        $isAdmin = in_array($user->role, ['super_admin', 'kepala_sekolah']);

        if (in_array($user->role, ['guru', 'bendahara'], true)) {
            $teacher = $user->teacher;

            if (!$teacher || ($leave->teacher_id != $teacher->id && $leave->infal_teacher_id != $teacher->id)) {
                abort(403, 'Anda tidak memiliki akses ke lampiran ini.');
            }
        } elseif (!$isAdmin) {
            abort(403, 'Anda tidak memiliki akses ke lampiran ini.');
        }

        if (!$leave->lampiran) {
            abort(404, 'Lampiran tidak ditemukan di database.');
        }

        $path = $this->resolveAttachmentPath($leave->lampiran);

        if (!$path) {
            abort(404, 'File lampiran tidak ditemukan.');
        }

        return response()->file($path);
    }

    private function validateLeaveRequestPayload(Request $request): array
    {
        $isSementara = $request->boolean('is_sementara');

        $rules = [
            'jenis_pengajuan' => 'required|in:sakit,izin,cuti,tugas_luar',
            'is_sementara' => 'nullable|boolean',
            'infal_teacher_id' => 'nullable|exists:teachers,id',
            'tanggal_mulai' => 'required|date',
            'alasan' => 'required|string',
            'lampiran' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ];

        if ($isSementara) {
            $rules['tanggal_selesai'] = 'nullable|date';
            $rules['jam_mulai'] = 'required|date_format:H:i';
            $rules['jam_selesai'] = 'required|date_format:H:i|after:jam_mulai';
        } else {
            $rules['tanggal_selesai'] = 'required|date|after_or_equal:tanggal_mulai';
            $rules['jam_mulai'] = 'nullable|date_format:H:i';
            $rules['jam_selesai'] = 'nullable|date_format:H:i';
        }

        $validated = $request->validate($rules, [
            'jenis_pengajuan.required' => 'Jenis pengajuan wajib dipilih.',
            'is_sementara.boolean' => 'Pilihan izin sementara tidak valid.',
            'tanggal_mulai.required' => 'Tanggal izin wajib diisi.',
            'tanggal_selesai.required' => 'Tanggal selesai wajib diisi.',
            'tanggal_selesai.after_or_equal' => 'Tanggal selesai tidak boleh lebih awal dari tanggal mulai.',
            'jam_mulai.required' => 'Jam mulai wajib diisi untuk izin sementara.',
            'jam_mulai.date_format' => 'Format jam mulai tidak valid.',
            'jam_selesai.required' => 'Jam selesai wajib diisi untuk izin sementara.',
            'jam_selesai.date_format' => 'Format jam selesai tidak valid.',
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
            'alasan.required' => 'Alasan wajib diisi.',
            'lampiran.mimes' => 'Lampiran harus berupa JPG, JPEG, PNG, atau PDF.',
            'lampiran.max' => 'Ukuran lampiran maksimal 2MB.',
        ]);

        $validated['is_sementara'] = $isSementara;
        $validated['infal_teacher_id'] = $validated['infal_teacher_id'] ?? null;

        if ($isSementara) {
            $validated['tanggal_selesai'] = $validated['tanggal_mulai'];
        } else {
            $validated['jam_mulai'] = null;
            $validated['jam_selesai'] = null;
        }

        $validated['jam_mulai'] = $validated['jam_mulai'] ?? null;
        $validated['jam_selesai'] = $validated['jam_selesai'] ?? null;

        return $validated;
    }


    private function storeAttachmentFile($file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $fileName = time() . '_' . uniqid('', true) . '.' . $extension;
        $relativePath = 'leave_attachments/' . $fileName;

        Storage::disk('local')->putFileAs('leave_attachments', $file, $fileName);

        return $relativePath;
    }

    private function resolveAttachmentPath(?string $relativePath): ?string
    {
        $relativePath = $this->normalizeAttachmentPath($relativePath);

        if (!$relativePath) {
            return null;
        }

        if (Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local')->path($relativePath);
        }

        foreach ([public_path($relativePath), storage_path('app/public/' . $relativePath)] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function deleteAttachmentFile(?string $relativePath): void
    {
        $relativePath = $this->normalizeAttachmentPath($relativePath);

        if (!$relativePath) {
            return;
        }

        if (Storage::disk('local')->exists($relativePath)) {
            Storage::disk('local')->delete($relativePath);
        }

        foreach ([public_path($relativePath), storage_path('app/public/' . $relativePath)] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function normalizeAttachmentPath(?string $relativePath): ?string
    {
        if (!$relativePath) {
            return null;
        }

        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');

        if (str_starts_with($relativePath, 'public/')) {
            $relativePath = substr($relativePath, strlen('public/'));
        }

        if (str_contains($relativePath, '..') || !str_starts_with($relativePath, 'leave_attachments/')) {
            return null;
        }

        return $relativePath;
    }


    private function resolvePerPage(Request $request, int $default = 7): int
    {
        $allowed = [7, 14, 21, 28, 35, 70];
        $perPage = (int) $request->input('per_page', $default);

        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }
}

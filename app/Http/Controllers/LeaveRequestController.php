<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\Teacher;
use App\Services\AppNotificationService;
use App\Services\AttendanceWindowService;
use App\Services\DailyAttendanceStatusService;
use App\Services\WorkScheduleResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class LeaveRequestController extends Controller
{
    private const REQUESTER_ROLES = ['guru', 'bendahara', 'tenaga_kependidikan'];

    private const APPROVER_ROLES = ['super_admin', 'kepala_sekolah'];

    public function index(Request $request)
    {
        $user = $request->user();
        $query = LeaveRequest::with(['teacher.user', 'infalTeachers.user', 'approver']);

        if (in_array($user->role, self::REQUESTER_ROLES, true)) {
            $teacher = $user->teacher;
            if (! $teacher) {
                return redirect()->route('dashboard')->with('error', 'Data guru untuk akun ini belum tersedia.');
            }

            $query->where(fn (Builder $query) => $query
                ->where('teacher_id', $teacher->id)
                ->orWhereHas('infalTeachers', fn (Builder $query) => $query->whereKey($teacher->id)));
        } elseif (! in_array($user->role, self::APPROVER_ROLES, true)) {
            abort(403);
        }

        return view('leave.index', ['leaves' => $query->latest()->paginate(10)]);
    }

    public function create(Request $request)
    {
        $teacher = $this->requesterTeacher($request);

        return view('leave.create', ['teachers' => $this->eligibleInfalTeachers($teacher->id)]);
    }

    public function store(Request $request)
    {
        $teacher = $this->requesterTeacher($request);
        $validated = $this->validateLeaveRequestPayload($request, $teacher->id);
        $newAttachment = $request->hasFile('lampiran')
            ? $this->storeAttachmentFile($request->file('lampiran'))
            : null;

        try {
            $leave = DB::transaction(function () use ($teacher, $validated, $newAttachment): LeaveRequest {
                $leave = LeaveRequest::create($this->leaveData($validated) + [
                    'teacher_id' => $teacher->id,
                    'lampiran' => $newAttachment,
                    'status_pengajuan' => 'pending',
                ]);
                $leave->infalTeachers()->attach($this->pendingPivotRows($validated['infal_teacher_ids']));

                return $leave;
            });
        } catch (Throwable $exception) {
            $this->deleteAttachmentFile($newAttachment);
            throw $exception;
        }

        AppNotificationService::sendToRoles(
            self::APPROVER_ROLES,
            'Pengajuan izin/cuti baru',
            ($teacher->nama_lengkap ?? 'Guru').' mengajukan '.$this->leaveTypeLabel($leave).' dan menunggu approval.',
            'leave',
            route('leave.index')
        );

        return redirect()->route('leave.index')->with('success', 'Pengajuan berhasil dikirim dan menunggu approval.');
    }

    public function edit(Request $request, LeaveRequest $leave)
    {
        $this->authorizeLeaveOwner($request, $leave);
        $leave->load('infalTeachers');
        $replaceRejected = ! $leave->is_sementara
            && $leave->status_pengajuan === 'disetujui'
            && $leave->infalTeachers->contains(fn (Teacher $teacher) => $teacher->pivot->status_infal === 'ditolak');

        if ($leave->status_pengajuan !== 'pending' && ! $replaceRejected) {
            return redirect()->route('leave.index')->with('error', 'Pengajuan ini tidak dapat diedit.');
        }

        return view('leave.edit', [
            'leave' => $leave,
            'teachers' => $this->eligibleInfalTeachers($leave->teacher_id),
            'modeGantiInfal' => $replaceRejected,
        ]);
    }

    public function update(Request $request, LeaveRequest $leave)
    {
        $this->authorizeLeaveOwner($request, $leave);
        $leave->load('infalTeachers');
        $replaceRejected = ! $leave->is_sementara
            && $leave->status_pengajuan === 'disetujui'
            && $leave->infalTeachers->contains(fn (Teacher $teacher) => $teacher->pivot->status_infal === 'ditolak');

        if ($replaceRejected) {
            $validated = $this->validateInfalTeachers($request, $leave->teacher_id);
            $approvedIds = $leave->infalTeachers
                ->filter(fn (Teacher $teacher) => $teacher->pivot->status_infal === 'disetujui')
                ->pluck('id')->all();
            $selectedIds = array_values(array_unique(array_merge($approvedIds, $validated['infal_teacher_ids'])));

            DB::transaction(function () use ($leave, $selectedIds, $approvedIds): void {
                $rows = [];
                foreach ($selectedIds as $teacherId) {
                    $rows[$teacherId] = [
                        'status_infal' => in_array($teacherId, $approvedIds, true) ? 'disetujui' : 'pending',
                        'catatan_infal' => null,
                    ];
                }
                $leave->infalTeachers()->sync($rows);
            });

            if ($leave->status_pengajuan === 'disetujui') {
                $this->notifyPendingInfals($leave->fresh('infalTeachers.user'));
            }

            return redirect()->route('leave.index')->with('success', 'Daftar guru pengganti berhasil diperbarui.');
        }

        if ($leave->status_pengajuan !== 'pending') {
            return redirect()->route('leave.index')->with('error', 'Pengajuan ini tidak dapat diedit karena sudah diproses.');
        }

        $validated = $this->validateLeaveRequestPayload($request, $leave->teacher_id, $leave);
        $oldAttachment = $leave->lampiran;
        $newAttachment = $request->hasFile('lampiran')
            ? $this->storeAttachmentFile($request->file('lampiran'))
            : null;

        try {
            DB::transaction(function () use ($leave, $validated, $newAttachment): void {
                $data = $this->leaveData($validated);
                if ($newAttachment) {
                    $data['lampiran'] = $newAttachment;
                }
                $leave->update($data);
                $leave->infalTeachers()->sync($this->pendingPivotRows($validated['infal_teacher_ids']));
            });
        } catch (Throwable $exception) {
            $this->deleteAttachmentFile($newAttachment);
            throw $exception;
        }

        if ($newAttachment) {
            $this->deleteAttachmentFile($oldAttachment);
        }

        return redirect()->route('leave.index')->with('success', 'Pengajuan berhasil diperbarui.');
    }

    public function destroy(Request $request, LeaveRequest $leave)
    {
        $this->authorizeLeaveOwner($request, $leave);
        if ($leave->status_pengajuan !== 'pending') {
            return redirect()->route('leave.index')->with('error', 'Pengajuan yang sudah diproses tidak dapat dihapus.');
        }

        $attachment = $leave->lampiran;
        DB::transaction(fn () => $leave->delete());
        $this->deleteAttachmentFile($attachment);

        return redirect()->route('leave.index')->with('success', 'Pengajuan berhasil dihapus.');
    }

    public function approve(Request $request, LeaveRequest $leave, DailyAttendanceStatusService $attendanceStatusService)
    {
        $this->authorizeApprover($request);
        $data = $request->validate(['catatan_approval' => 'nullable|string|max:500']);
        $leave = DB::transaction(function () use ($leave, $request, $data): ?LeaveRequest {
            $locked = LeaveRequest::query()->lockForUpdate()->findOrFail($leave->id);
            if ($locked->status_pengajuan !== 'pending') {
                return null;
            }
            $locked->update([
                'status_pengajuan' => 'disetujui',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'catatan_approval' => $data['catatan_approval'] ?? null,
            ]);

            return $locked->fresh(['teacher.user', 'infalTeachers.user']);
        });

        if (! $leave) {
            return back()->with('error', 'Pengajuan ini sudah diproses.');
        }

        if ($leave->is_sementara) {
            $attendanceStatusService->syncTeacherForDate($leave->teacher, $leave->tanggal_mulai);
        }

        if ($leave->teacher?->user_id) {
            $message = 'Pengajuan '.$this->leaveTypeLabel($leave).' Anda disetujui.';
            if (! empty($data['catatan_approval'])) {
                $message .= ' Catatan: '.$data['catatan_approval'];
            }
            AppNotificationService::send($leave->teacher->user_id, 'Pengajuan izin/cuti disetujui', $message, 'leave', route('leave.index'));
        }
        $this->notifyPendingInfals($leave->fresh('infalTeachers.user'));

        return back()->with('success', 'Pengajuan berhasil disetujui.');
    }

    public function reject(Request $request, LeaveRequest $leave)
    {
        $this->authorizeApprover($request);
        $data = $request->validate(['catatan_approval' => 'nullable|string|max:500']);
        $leave = DB::transaction(function () use ($leave, $request, $data): ?LeaveRequest {
            $locked = LeaveRequest::query()->lockForUpdate()->findOrFail($leave->id);
            if ($locked->status_pengajuan !== 'pending') {
                return null;
            }
            $locked->update([
                'status_pengajuan' => 'ditolak',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'catatan_approval' => $data['catatan_approval'] ?? null,
            ]);
            $locked->infalTeachers()->newPivotStatement()
                ->where('leave_request_id', $locked->id)
                ->update(['status_infal' => 'ditolak', 'updated_at' => now()]);

            return $locked->fresh('teacher.user');
        });

        if (! $leave) {
            return back()->with('error', 'Pengajuan ini sudah diproses.');
        }

        if ($leave->teacher?->user_id) {
            $message = 'Pengajuan '.$this->leaveTypeLabel($leave).' Anda ditolak.';
            if (! empty($data['catatan_approval'])) {
                $message .= ' Catatan: '.$data['catatan_approval'];
            }
            AppNotificationService::send($leave->teacher->user_id, 'Pengajuan izin/cuti ditolak', $message, 'leave', route('leave.index'));
        }

        return back()->with('success', 'Pengajuan berhasil ditolak.');
    }

    public function approveInfal(Request $request, LeaveRequest $leave)
    {
        $teacher = $this->requesterTeacher($request);
        $result = $this->updateInfalStatus($leave, $teacher, 'disetujui');
        if ($result === 'leave_pending') {
            return back()->with('error', 'Pengajuan utama belum disetujui.');
        }
        if ($result === 'forbidden') {
            abort(403, 'Anda tidak memiliki akses untuk menyetujui penggantian ini.');
        }
        if ($result === 'processed') {
            return back()->with('error', 'Status guru pengganti sudah diproses.');
        }

        $leave = $leave->fresh('teacher.user');
        $this->notifyRequesterOfInfal($leave, 'Guru pengganti menyetujui', ($teacher->nama_lengkap ?? 'Guru pengganti').' menyetujui permintaan infal Anda.');

        return back()->with('success', 'Anda menyetujui sebagai guru pengganti.');
    }

    public function rejectInfal(Request $request, LeaveRequest $leave)
    {
        $teacher = $this->requesterTeacher($request);
        $data = $request->validate(['catatan_infal' => 'required|string|max:500']);
        $result = $this->updateInfalStatus($leave, $teacher, 'ditolak', $data['catatan_infal']);
        if ($result === 'leave_pending') {
            return back()->with('error', 'Pengajuan utama belum disetujui.');
        }
        if ($result === 'forbidden') {
            abort(403, 'Anda tidak memiliki akses untuk menolak penggantian ini.');
        }
        if ($result === 'processed') {
            return back()->with('error', 'Status guru pengganti sudah diproses.');
        }

        $leave = $leave->fresh('teacher.user');
        $this->notifyRequesterOfInfal($leave, 'Guru pengganti menolak', ($teacher->nama_lengkap ?? 'Guru pengganti').' menolak permintaan infal. Catatan: '.$data['catatan_infal']);

        return back()->with('success', 'Anda menolak sebagai guru pengganti.');
    }

    public function showAttachment(Request $request, LeaveRequest $leave)
    {
        $user = $request->user();
        if (in_array($user->role, self::REQUESTER_ROLES, true)) {
            $teacher = $user->teacher;
            if (! $teacher || ($leave->teacher_id !== $teacher->id && ! $leave->infalTeachers()->whereKey($teacher->id)->exists())) {
                abort(403, 'Anda tidak memiliki akses ke lampiran ini.');
            }
        } elseif (! in_array($user->role, self::APPROVER_ROLES, true)) {
            abort(403, 'Anda tidak memiliki akses ke lampiran ini.');
        }

        if (! $leave->lampiran) {
            abort(404, 'Lampiran tidak ditemukan di database.');
        }
        $path = $this->resolveAttachmentPath($leave->lampiran);
        if (! $path) {
            abort(404, 'File lampiran tidak ditemukan.');
        }

        $response = response()->file($path);
        $response->setPrivate();
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    private function requesterTeacher(Request $request): Teacher
    {
        if (! in_array($request->user()->role, self::REQUESTER_ROLES, true)) {
            abort(403);
        }
        $teacher = $request->user()->teacher;
        if (! $teacher) {
            abort(403, 'Data guru untuk akun ini belum tersedia.');
        }

        return $teacher;
    }

    private function authorizeApprover(Request $request): void
    {
        if (! in_array($request->user()->role, self::APPROVER_ROLES, true)) {
            abort(403);
        }
    }

    private function authorizeLeaveOwner(Request $request, LeaveRequest $leave): void
    {
        if (! in_array($request->user()->role, self::REQUESTER_ROLES, true)
            || ! $request->user()->teacher
            || $leave->teacher_id !== $request->user()->teacher->id) {
            abort(403, 'Anda tidak memiliki akses ke pengajuan ini.');
        }
    }

    private function eligibleInfalTeachers(int $requesterId)
    {
        return Teacher::query()
            ->whereKeyNot($requesterId)
            ->whereHas('user', fn (Builder $query) => $query
                ->where('status', 'aktif')
                ->whereIn('role', Teacher::DATA_GURU_ROLES))
            ->with('user')
            ->orderBy('nama_lengkap')
            ->get();
    }

    private function validateInfalTeachers(Request $request, int $requesterId): array
    {
        return $request->validate([
            'infal_teacher_ids' => ['nullable', 'array'],
            'infal_teacher_ids.*' => [
                'integer',
                'distinct',
                Rule::notIn([$requesterId]),
                Rule::exists('teachers', 'id')->where(fn ($query) => $query
                    ->whereExists(fn ($query) => $query->selectRaw('1')
                        ->from('users')
                        ->whereColumn('users.id', 'teachers.user_id')
                        ->where('users.status', 'aktif')
                        ->whereIn('users.role', Teacher::DATA_GURU_ROLES))),
            ],
        ], [
            'infal_teacher_ids.*.distinct' => 'Guru pengganti tidak boleh dipilih lebih dari sekali.',
            'infal_teacher_ids.*.not_in' => 'Guru pengganti tidak boleh sama dengan guru yang mengajukan.',
            'infal_teacher_ids.*.exists' => 'Guru pengganti harus memiliki akun aktif dengan role data guru.',
        ]);
    }

    private function validateLeaveRequestPayload(Request $request, int $requesterId, ?LeaveRequest $existing = null): array
    {
        $isSementara = $request->boolean('is_sementara');
        $rules = [
            'is_sementara' => 'nullable|boolean',
            'tanggal_mulai' => 'required|date',
            'alasan' => 'required|string|max:2000',
            'lampiran' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ];
        $rules += $isSementara
            ? ['tanggal_selesai' => 'nullable|date', 'jam_mulai' => 'required|date_format:H:i', 'jam_selesai' => 'required|date_format:H:i|after:jam_mulai']
            : [
                'jenis_pengajuan' => 'required|in:sakit,izin,cuti,tugas_luar',
                'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
                'jam_mulai' => 'nullable|date_format:H:i',
                'jam_selesai' => 'nullable|date_format:H:i',
            ];

        if (! $isSementara) {
            $rules += [
                'infal_teacher_ids' => ['nullable', 'array'],
                'infal_teacher_ids.*' => [
                    'integer', 'distinct', Rule::notIn([$requesterId]),
                    Rule::exists('teachers', 'id')->where(fn ($query) => $query
                        ->whereExists(fn ($query) => $query->selectRaw('1')->from('users')
                            ->whereColumn('users.id', 'teachers.user_id')->where('users.status', 'aktif')
                            ->whereIn('users.role', Teacher::DATA_GURU_ROLES))),
                ],
            ];
        }

        $validator = validator($request->all(), $rules);

        $validator->after(function ($validator) use ($request, $requesterId, $isSementara, $existing): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $date = Carbon::parse($request->input('tanggal_mulai'), 'Asia/Jakarta')->startOfDay();
            $endDate = Carbon::parse($request->input('tanggal_selesai') ?: $request->input('tanggal_mulai'), 'Asia/Jakarta')->startOfDay();
            $overlap = LeaveRequest::query()
                ->where('teacher_id', $requesterId)
                ->whereIn('status_pengajuan', ['pending', 'disetujui'])
                ->when($existing, fn (Builder $query) => $query->whereKeyNot($existing->id))
                ->whereDate('tanggal_mulai', '<=', $endDate->toDateString())
                ->whereDate('tanggal_selesai', '>=', $date->toDateString());

            if (! $isSementara && $overlap->exists()) {
                $validator->errors()->add('tanggal_mulai', 'Rentang tanggal tumpang tindih dengan pengajuan pending atau disetujui yang sudah ada.');

                return;
            }

            if (! $isSementara) {
                return;
            }

            if ((clone $overlap)->where('is_sementara', false)->exists()) {
                $validator->errors()->add('tanggal_mulai', 'Tanggal izin sementara sudah tercakup pengajuan sehari penuh yang pending atau disetujui.');

                return;
            }

            $dateChanged = ! $existing || ! $existing->tanggal_mulai?->isSameDay($date);
            if ($dateChanged && $date->isBefore(now('Asia/Jakarta')->startOfDay())) {
                $validator->errors()->add('tanggal_mulai', 'Tanggal izin sementara baru tidak boleh sudah lewat.');
            }

            if ($existing && ! $dateChanged && $date->isBefore(now('Asia/Jakarta')->startOfDay())) {
                return;
            }

            $start = Carbon::parse($date->toDateString().' '.$request->input('jam_mulai'), 'Asia/Jakarta');
            $end = Carbon::parse($date->toDateString().' '.$request->input('jam_selesai'), 'Asia/Jakarta');
            $teacher = Teacher::find($requesterId);
            $sessions = $teacher
                ? app(WorkScheduleResolver::class)->sessionsForDate($teacher, $date)
                    ->filter(function ($session) use ($date, $start, $end): bool {
                        $sessionStart = app(AttendanceWindowService::class)->resolve($session, $date)['jam_masuk'];

                        return $sessionStart->betweenIncluded($start, $end);
                    })
                : collect();

            if ($sessions->isEmpty()) {
                $validator->errors()->add('jam_mulai', 'Interval izin sementara harus mencakup jam masuk minimal satu sesi aktif yang ditugaskan.');
            }

            if ($sessions->contains(function ($session) use ($date, $end): bool {
                $sessionEnd = app(AttendanceWindowService::class)->resolve($session, $date)['jam_pulang'];

                return $end->copy()->addMinutes(30)->gte($sessionEnd);
            })) {
                $validator->errors()->add('jam_selesai', 'Izin sementara beserta grace mencapai akhir sesi. Ajukan izin sehari penuh.');
            }

            $overlap = LeaveRequest::query()
                ->where('teacher_id', $requesterId)
                ->where('is_sementara', true)
                ->whereDate('tanggal_mulai', $date->toDateString())
                ->whereIn('status_pengajuan', ['pending', 'disetujui'])
                ->when($existing, fn (Builder $query) => $query->whereKeyNot($existing->id))
                ->whereTime('jam_mulai', '<=', $end->format('H:i:s'))
                ->whereTime('jam_selesai', '>=', $start->format('H:i:s'))
                ->exists();

            if ($overlap) {
                $validator->errors()->add('jam_mulai', 'Interval izin sementara tumpang tindih dengan pengajuan pending atau disetujui pada tanggal sama.');
            }
        });

        $validated = $validator->validate();

        $validated['is_sementara'] = $isSementara;
        if ($isSementara) {
            $validated['jenis_pengajuan'] = 'izin';
            $validated['tanggal_selesai'] = $validated['tanggal_mulai'];
            $validated['infal_teacher_ids'] = [];
        } else {
            $validated['infal_teacher_ids'] = array_map('intval', $validated['infal_teacher_ids'] ?? []);
            $validated['jam_mulai'] = null;
            $validated['jam_selesai'] = null;
        }

        return $validated;
    }

    private function leaveData(array $validated): array
    {
        return [
            'jenis_pengajuan' => $validated['jenis_pengajuan'],
            'is_sementara' => $validated['is_sementara'],
            'tanggal_mulai' => $validated['tanggal_mulai'],
            'tanggal_selesai' => $validated['tanggal_selesai'],
            'jam_mulai' => $validated['jam_mulai'] ?? null,
            'jam_selesai' => $validated['jam_selesai'] ?? null,
            'alasan' => $validated['alasan'],
        ];
    }

    private function pendingPivotRows(array $teacherIds): array
    {
        return array_fill_keys($teacherIds, ['status_infal' => 'pending', 'catatan_infal' => null]);
    }

    private function leaveTypeLabel(LeaveRequest $leave): string
    {
        return $leave->is_sementara
            ? 'Izin Sementara'
            : ucfirst(str_replace('_', ' ', $leave->jenis_pengajuan));
    }

    private function notifyPendingInfals(LeaveRequest $leave): void
    {
        foreach ($leave->infalTeachers as $teacher) {
            if ($teacher->pivot->status_infal === 'pending' && $teacher->user_id) {
                AppNotificationService::send($teacher->user_id, 'Permintaan menjadi guru pengganti', 'Anda dipilih sebagai guru pengganti untuk '.($leave->teacher->nama_lengkap ?? 'guru').'.', 'infal', route('leave.index'));
            }
        }
    }

    private function notifyRequesterOfInfal(LeaveRequest $leave, string $title, string $message): void
    {
        if ($leave->teacher?->user_id) {
            AppNotificationService::send($leave->teacher->user_id, $title, $message, 'infal', route('leave.index'));
        }
    }

    private function storeAttachmentFile(UploadedFile $file): string
    {
        $extension = match (strtolower((string) $file->extension())) {
            'jpeg', 'jpg' => 'jpg',
            'png' => 'png',
            'pdf' => 'pdf',
            default => abort(422, 'Format lampiran tidak didukung.'),
        };
        $fileName = time().'_'.uniqid('', true).'.'.$extension;
        Storage::disk('local')->putFileAs('leave_attachments', $file, $fileName);

        return 'leave_attachments/'.$fileName;
    }

    private function resolveAttachmentPath(?string $relativePath): ?string
    {
        $relativePath = $this->normalizeAttachmentPath($relativePath);
        if (! $relativePath) {
            return null;
        }
        if (Storage::disk('local')->exists($relativePath)) {
            return Storage::disk('local')->path($relativePath);
        }

        return null;
    }

    private function deleteAttachmentFile(?string $relativePath): void
    {
        $relativePath = $this->normalizeAttachmentPath($relativePath);
        if (! $relativePath) {
            return;
        }
        Storage::disk('local')->delete($relativePath);
        foreach ([public_path($relativePath), storage_path('app/public/'.$relativePath)] as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function normalizeAttachmentPath(?string $relativePath): ?string
    {
        if (! $relativePath) {
            return null;
        }
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if (str_starts_with($relativePath, 'public/')) {
            $relativePath = substr($relativePath, strlen('public/'));
        }

        return str_contains($relativePath, '..') || ! str_starts_with($relativePath, 'leave_attachments/') ? null : $relativePath;
    }

    private function updateInfalStatus(
        LeaveRequest $leave,
        Teacher $teacher,
        string $status,
        ?string $note = null
    ): string {
        return DB::transaction(function () use ($leave, $teacher, $status, $note): string {
            $lockedLeave = LeaveRequest::query()->lockForUpdate()->findOrFail($leave->id);
            if ($lockedLeave->status_pengajuan !== 'disetujui') {
                return 'leave_pending';
            }

            $pivot = DB::table('leave_request_infal')
                ->where('leave_request_id', $lockedLeave->id)
                ->where('teacher_id', $teacher->id)
                ->lockForUpdate()
                ->first();

            if (! $pivot) {
                return 'forbidden';
            }
            if ($pivot->status_infal !== 'pending') {
                return 'processed';
            }

            DB::table('leave_request_infal')
                ->where('id', $pivot->id)
                ->update([
                    'status_infal' => $status,
                    'catatan_infal' => $note,
                    'updated_at' => now(),
                ]);

            return 'updated';
        });
    }
}

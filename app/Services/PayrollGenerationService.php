<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\LeaveRequest;
use App\Models\PayrollAuditLog;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollGenerationService
{
    private const DEDUCTED_LEAVE_TYPES = ['sakit', 'izin', 'cuti'];

    public function __construct(private readonly WorkScheduleResolver $scheduleResolver) {}

    public function generate(int $year, int $month, int $actorId): PayrollPeriod
    {
        return DB::transaction(function () use ($year, $month, $actorId): PayrollPeriod {
            $start = Carbon::create($year, $month, 1, 0, 0, 0, config('app.timezone'))->startOfMonth();
            $end = $start->copy()->endOfMonth();
            $period = PayrollPeriod::query()
                ->where('tahun', $year)
                ->where('bulan', $month)
                ->lockForUpdate()
                ->first();

            if ($period?->status === 'final') {
                throw new DomainException('Periode payroll sudah final. Super admin harus membukanya kembali sebelum generate ulang.');
            }

            $period ??= new PayrollPeriod(['tahun' => $year, 'bulan' => $month]);
            $period->fill([
                'tanggal_mulai' => $start->toDateString(),
                'tanggal_selesai' => $end->toDateString(),
                'status' => 'draft',
                'generated_by' => $actorId,
            ])->save();

            $period->items()->delete();
            $items = $this->createBaseItems($period);
            $this->applyApprovedLeaves($items, $start, $end);
            $this->applyAlphaDeductions($items, $start, $end);
            $this->recalculateTotals($items);

            PayrollAuditLog::create([
                'payroll_period_id' => $period->id,
                'user_id' => $actorId,
                'action' => 'generated',
                'metadata' => ['year' => $year, 'month' => $month, 'teacher_count' => count($items)],
            ]);

            return $period->fresh(['items']);
        });
    }

    /**
     * @return array<int, PayrollItem>
     */
    private function createBaseItems(PayrollPeriod $period): array
    {
        $teachers = Teacher::with(['user', 'salary', 'schedules.attendanceSession'])
            ->whereHas('user', fn ($query) => $query
                ->where('status', 'aktif')
                ->whereIn('role', User::ATTENDANCE_ROLES))
            ->orderBy('nama_lengkap')
            ->get();
        $items = [];

        foreach ($teachers as $teacher) {
            $salary = $teacher->salary ?: $this->ensureTeacherSalary($teacher);
            $items[$teacher->id] = PayrollItem::create([
                'payroll_period_id' => $period->id,
                'teacher_id' => $teacher->id,
                'gaji_pokok' => $salary->gaji_pokok,
                'potongan_absen' => 0,
                'tambahan_infal' => 0,
                'gaji_bersih' => $salary->gaji_pokok,
                'jumlah_absen_diganti' => 0,
                'jumlah_mengganti' => 0,
                'catatan' => 'Tidak hadir: 0x; Mengganti: 0x',
            ]);
        }

        return $items;
    }

    private function applyApprovedLeaves(array $items, Carbon $start, Carbon $end): void
    {
        $leaves = LeaveRequest::with(['teacher.salary', 'teacher.schedules.attendanceSession', 'infalTeachers'])
            ->where('status_pengajuan', 'disetujui')
            ->whereIn('jenis_pengajuan', self::DEDUCTED_LEAVE_TYPES)
            ->where('is_sementara', false)
            ->whereHas('infalTeachers', fn ($query) => $query->where('leave_request_infal.status_infal', 'disetujui'))
            ->whereDate('tanggal_mulai', '<=', $end->toDateString())
            ->whereDate('tanggal_selesai', '>=', $start->toDateString())
            ->orderBy('tanggal_mulai')
            ->get();

        foreach ($leaves as $leave) {
            $mainTeacher = $leave->teacher;
            $approvedInfals = $leave->infalTeachers
                ->filter(fn (Teacher $teacher) => $teacher->pivot->status_infal === 'disetujui' && isset($items[$teacher->id]))
                ->sortBy('id')
                ->values();

            if (! $mainTeacher || ! isset($items[$mainTeacher->id]) || $approvedInfals->isEmpty()) {
                continue;
            }

            $salary = $mainTeacher->salary ?: $this->ensureTeacherSalary($mainTeacher);
            $deduction = round((float) $salary->potongan_per_absen, 2);
            $shares = $this->splitAmount($deduction, $approvedInfals->count());
            $dates = $this->effectiveLeaveDates($mainTeacher, $leave, $start, $end);
            $replacementNames = $approvedInfals->pluck('nama_lengkap')->implode(', ');

            foreach ($dates as $date) {
                $mainItem = $items[$mainTeacher->id];
                $mainItem->increment('potongan_absen', $deduction);
                $mainItem->increment('jumlah_absen_diganti');
                $mainItem->details()->create([
                    'leave_request_id' => $leave->id,
                    'tanggal_event' => $date->toDateString(),
                    'tipe' => 'potongan_absen',
                    'nominal' => $deduction,
                    'keterangan' => 'Potongan '.str_replace('_', ' ', $leave->jenis_pengajuan).' karena digantikan oleh '.$replacementNames.'.',
                ]);

                foreach ($approvedInfals as $index => $infalTeacher) {
                    $share = $shares[$index];
                    $bonusItem = $items[$infalTeacher->id];
                    $bonusItem->increment('tambahan_infal', $share);
                    $bonusItem->increment('jumlah_mengganti');
                    $bonusItem->details()->create([
                        'leave_request_id' => $leave->id,
                        'tanggal_event' => $date->toDateString(),
                        'tipe' => 'tambahan_infal',
                        'nominal' => $share,
                        'keterangan' => 'Tambahan infal menggantikan '.($mainTeacher->nama_lengkap ?? 'Guru').'.',
                    ]);
                }
            }
        }
    }

    private function applyAlphaDeductions(array $items, Carbon $start, Carbon $end): void
    {
        $groups = Attendance::with(['teacher.salary', 'attendanceSession'])
            ->where('status_kehadiran', 'alfa')
            ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
            ->orderBy('tanggal')
            ->get()
            ->groupBy(fn (Attendance $attendance) => $attendance->teacher_id.'|'.$attendance->tanggal->toDateString());

        foreach ($groups as $group) {
            $attendance = $group->first();
            $teacher = $attendance->teacher;
            if (! $teacher || ! isset($items[$teacher->id])) {
                continue;
            }

            $salary = $teacher->salary ?: $this->ensureTeacherSalary($teacher);
            $deduction = round((float) $salary->potongan_per_absen, 2);
            $item = $items[$teacher->id];
            $sessionNames = $group->pluck('attendanceSession.nama_sesi')->filter()->unique()->implode(', ');
            $item->increment('potongan_absen', $deduction);
            $item->increment('jumlah_absen_diganti');
            $item->details()->create([
                'leave_request_id' => null,
                'tanggal_event' => $attendance->tanggal->toDateString(),
                'tipe' => 'potongan_absen',
                'nominal' => $deduction,
                'keterangan' => 'Potongan alfa/tidak hadir'.($sessionNames ? '. Sesi terlewat: '.$sessionNames : '').'. Dana masuk kas sekolah.',
            ]);
        }
    }

    private function effectiveLeaveDates(Teacher $teacher, LeaveRequest $leave, Carbon $periodStart, Carbon $periodEnd): Collection
    {
        $start = $leave->tanggal_mulai->copy()->max($periodStart);
        $end = $leave->tanggal_selesai->copy()->min($periodEnd);
        $hasSchedule = $this->scheduleResolver->hasAnySchedule($teacher);

        return collect(CarbonPeriod::create($start, $end))
            ->filter(fn (Carbon $date) => ! $hasSchedule || $this->scheduleResolver->sessionsForDate($teacher, $date)->isNotEmpty())
            ->map(fn (Carbon $date) => $date->copy())
            ->values();
    }

    /**
     * @return array<int, float>
     */
    private function splitAmount(float $amount, int $recipients): array
    {
        $totalCents = (int) round($amount * 100);
        $baseCents = intdiv($totalCents, $recipients);
        $remainder = $totalCents % $recipients;

        return collect(range(0, $recipients - 1))
            ->map(fn (int $index) => ($baseCents + ($index < $remainder ? 1 : 0)) / 100)
            ->all();
    }

    private function recalculateTotals(array $items): void
    {
        foreach ($items as $item) {
            $item->refresh();
            $item->update([
                'gaji_bersih' => (float) $item->gaji_pokok - (float) $item->potongan_absen + (float) $item->tambahan_infal,
                'catatan' => 'Tidak hadir: '.$item->jumlah_absen_diganti.'x; Mengganti: '.$item->jumlah_mengganti.'x',
            ]);
        }
    }

    private function ensureTeacherSalary(Teacher $teacher): TeacherSalary
    {
        return TeacherSalary::firstOrCreate(
            ['teacher_id' => $teacher->id],
            [
                'gaji_pokok' => 0,
                'potongan_per_absen' => $this->defaultDeduction(0),
                'keterangan' => 'Nilai awal otomatis; dapat diubah bendahara.',
            ],
        );
    }

    private function defaultDeduction(float $baseSalary): float
    {
        return $baseSalary >= 1000000 ? 30000 : 20000;
    }
}

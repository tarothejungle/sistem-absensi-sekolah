<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use App\Models\PayrollItem;
use App\Models\PayrollItemDetail;
use App\Models\PayrollPeriod;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    private array $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    private array $payrollRoles = ['bendahara', 'super_admin'];

    private array $payrollTeacherRoles = ['guru', 'bendahara', 'kepala_sekolah'];

    private array $deductedLeaveTypes = ['sakit', 'izin', 'cuti'];

    public function index(Request $request)
    {
        $this->authorizePayrollAccess();

        $selectedYear = (int) $request->input('tahun', now()->year);
        $selectedMonth = (int) $request->input('bulan', now()->month);
        // $perPage = $this->resolvePerPage($request);

        $periods = PayrollPeriod::with('generator')
            ->orderBy('tahun', 'desc')
            ->orderBy('bulan', 'desc')
            ->paginate(10);
            // ->withQueryString()

        $summaryPeriod = PayrollPeriod::with('items')
            ->where('tahun', $selectedYear)
            ->where('bulan', $selectedMonth)
            ->first();

        return view('payroll.index', [
            'periods' => $periods,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
            'monthNames' => $this->monthNames,
            'summaryPeriod' => $summaryPeriod,
        ]);
    }

    public function settings(Request $request)
    {
        $this->authorizePayrollAccess();
        // $perPage = $this->resolvePerPage($request);
        $payrollTeacherRoles = $this->payrollTeacherRoles;

        $teachers = Teacher::with(['user', 'salary'])
            ->whereHas('user', function ($query) use ($payrollTeacherRoles) {
                $query->whereIn('role', $payrollTeacherRoles);
            })
            ->orderBy('nama_lengkap')
            ->paginate(7)
            ->withQueryString();

        $teachers->getCollection()->each(function (Teacher $teacher) {
            if (!$teacher->salary) {
                $teacher->setRelation('salary', $this->ensureTeacherSalary($teacher));
            }
        });

        return view('payroll.settings', compact('teachers'));
    }

    public function updateSetting(Request $request, Teacher $teacher)
    {
        $this->authorizePayrollAccess();

        $data = $request->validate([
            'gaji_pokok' => 'required|numeric|min:0',
            'potongan_per_absen' => 'nullable|numeric|min:0',
            'keterangan' => 'nullable|string|max:500',
        ]);

        $potongan = $data['potongan_per_absen']
            ?? $this->getPotonganPerAbsen((float) $data['gaji_pokok']);

        TeacherSalary::updateOrCreate(
            ['teacher_id' => $teacher->id],
            [
                'gaji_pokok' => $data['gaji_pokok'],
                'potongan_per_absen' => $potongan,
                'keterangan' => $data['keterangan'] ?? null,
            ]
        );

        return back()->with('success', 'Pengaturan gaji ' . ($teacher->nama_lengkap ?? 'guru') . ' berhasil diperbarui.');
    }

    public function generate(Request $request)
    {
        $this->authorizePayrollAccess();

        $request->validate([
            'tahun' => 'required|integer|min:2020|max:2100',
            'bulan' => 'required|integer|min:1|max:12',
        ]);

        $tahun = (int) $request->tahun;
        $bulan = (int) $request->bulan;

        $tanggalMulai = Carbon::create($tahun, $bulan, 1)->startOfMonth();
        $tanggalSelesai = Carbon::create($tahun, $bulan, 1)->endOfMonth();
        $payrollTeacherRoles = $this->payrollTeacherRoles;
        $deductedLeaveTypes = $this->deductedLeaveTypes;

        DB::transaction(function () use ($tahun, $bulan, $tanggalMulai, $tanggalSelesai, $payrollTeacherRoles, $deductedLeaveTypes) {
            $period = PayrollPeriod::updateOrCreate(
                [
                    'tahun' => $tahun,
                    'bulan' => $bulan,
                ],
                [
                    'tanggal_mulai' => $tanggalMulai->toDateString(),
                    'tanggal_selesai' => $tanggalSelesai->toDateString(),
                    'status' => 'draft',
                    'generated_by' => auth()->id(),
                ]
            );

            /*
            |------------------------------------------------------------
            | Buat ulang payroll item agar data lama tidak tersisa.
            | Detail payroll otomatis ikut terhapus karena cascade.
            |------------------------------------------------------------
            */
            $period->items()->delete();

            $teachers = Teacher::with([
                'user',
                'salary',
                'schedules',
            ])
                ->whereHas('user', function ($query) use ($payrollTeacherRoles) {
                    $query->where('status', 'aktif')
                        ->whereIn('role', $payrollTeacherRoles);
                })
                ->orderBy('nama_lengkap')
                ->get();

            $payrollItems = [];

            /*
            |------------------------------------------------------------
            | Buat payroll awal semua guru.
            |------------------------------------------------------------
            */
            foreach ($teachers as $teacher) {
                $salary = $teacher->salary ?: $this->ensureTeacherSalary($teacher);

                $payrollItem = PayrollItem::create([
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

                $payrollItems[$teacher->id] = $payrollItem;
            }

            /*
            |------------------------------------------------------------
            | Ambil pengajuan izin/cuti yang:
            | - sudah disetujui admin/kepsek
            | - guru infalnya juga sudah menyetujui
            | - memiliki guru pengganti
            | - tanggalnya overlap dengan periode payroll
            |------------------------------------------------------------
            */
            $leaveRequests = LeaveRequest::with([
                'teacher.salary',
                'teacher.schedules',
                'infalTeacher',
            ])
                ->where('status_pengajuan', 'disetujui')
                ->where('status_infal', 'disetujui')
                ->whereNotNull('infal_teacher_id')
                ->whereIn('jenis_pengajuan', $deductedLeaveTypes)
                ->whereDate('tanggal_mulai', '<=', $tanggalSelesai->toDateString())
                ->whereDate('tanggal_selesai', '>=', $tanggalMulai->toDateString())
                ->get();

            foreach ($leaveRequests as $leave) {
                $guruUtama = $leave->teacher;
                $guruInfal = $leave->infalTeacher;

                if (!$guruUtama || !$guruInfal) {
                    continue;
                }

                if (!isset($payrollItems[$guruUtama->id])) {
                    continue;
                }

                if (!isset($payrollItems[$guruInfal->id])) {
                    continue;
                }

                $salaryGuruUtama = $guruUtama->salary;

                $salaryGuruUtama = $salaryGuruUtama ?: $this->ensureTeacherSalary($guruUtama);

                /*
                | Potongan mengikuti nominal guru yang tidak hadir,
                | bukan nominal gaji guru pengganti.
                */
                $gajiPokokGuruUtama = (float) $salaryGuruUtama->gaji_pokok;

                $potonganPerHari = $this->getPotonganPerAbsen($gajiPokokGuruUtama);

                if ((float) $salaryGuruUtama->potongan_per_absen !== $potonganPerHari) {
                    $salaryGuruUtama->update([
                        'potongan_per_absen' => $potonganPerHari,
                    ]);
                }

                $mulai = Carbon::parse($leave->tanggal_mulai)
                    ->greaterThan($tanggalMulai)
                        ? Carbon::parse($leave->tanggal_mulai)
                        : $tanggalMulai->copy();

                $selesai = Carbon::parse($leave->tanggal_selesai)
                    ->lessThan($tanggalSelesai)
                        ? Carbon::parse($leave->tanggal_selesai)
                        : $tanggalSelesai->copy();

                /*
                | Hitung hanya hari aktif mengajar guru utama.
                | Jika guru belum punya jadwal hari kerja,
                | sistem tetap menghitung semua tanggal dalam izin.
                */
                $tanggalEfektif = $this->getEffectiveLeaveDates(
                    $guruUtama,
                    $mulai,
                    $selesai
                );

                foreach ($tanggalEfektif as $tanggal) {
                    $itemGuruUtama = $payrollItems[$guruUtama->id];
                    $itemGuruInfal = $payrollItems[$guruInfal->id];

                    /*
                    | Potong guru utama.
                    */
                    $itemGuruUtama->increment('potongan_absen', $potonganPerHari);
                    $itemGuruUtama->increment('jumlah_absen_diganti', 1);

                    $itemGuruUtama->details()->create([
                        'leave_request_id' => $leave->id,
                        'tanggal_event' => $tanggal->toDateString(),
                        'tipe' => 'potongan_absen',
                        'nominal' => $potonganPerHari,
                        'keterangan' => 'Potongan karena digantikan oleh '
                            . ($guruInfal->nama_lengkap ?? 'Guru Infal'),
                    ]);

                    /*
                    | Tambahkan nominal yang sama ke guru infal.
                    */
                    $itemGuruInfal->increment('tambahan_infal', $potonganPerHari);
                    $itemGuruInfal->increment('jumlah_mengganti', 1);

                    $itemGuruInfal->details()->create([
                        'leave_request_id' => $leave->id,
                        'tanggal_event' => $tanggal->toDateString(),
                        'tipe' => 'tambahan_infal',
                        'nominal' => $potonganPerHari,
                        'keterangan' => 'Tambahan infal menggantikan '
                            . ($guruUtama->nama_lengkap ?? 'Guru'),
                    ]);
                }
            }

            /*
            |------------------------------------------------------------
            | Hitung ulang gaji bersih serta catatan akhir.
            |------------------------------------------------------------
            */
            foreach ($payrollItems as $item) {
                $item->refresh();

                $gajiBersih = (float) $item->gaji_pokok
                    - (float) $item->potongan_absen
                    + (float) $item->tambahan_infal;

                $item->update([
                    'gaji_bersih' => $gajiBersih,
                    'catatan' => 'Tidak hadir: '
                        . $item->jumlah_absen_diganti
                        . 'x; Mengganti: '
                        . $item->jumlah_mengganti
                        . 'x',
                ]);
            }
        });

        return redirect()
            ->route('payroll.index')
            ->with('success', 'Penggajian berhasil dibuat ulang.');
    }

    public function show(Request $request, PayrollPeriod $period)
    {
        $this->authorizePayrollAccess();
        // $perPage = $this->resolvePerPage($request);

        $items = PayrollItem::with(['teacher.user', 'details.leaveRequest.teacher', 'details.leaveRequest.infalTeacher'])
            ->where('payroll_period_id', $period->id)
            ->orderByRaw('(gaji_pokok - potongan_absen + tambahan_infal) desc')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('payroll.show', compact('period', 'items'));
    }

    public function print(PayrollPeriod $period)
    {
        $this->authorizePayrollAccess();

        $period->load(['items.teacher.user', 'items.details.leaveRequest.teacher', 'items.details.leaveRequest.infalTeacher']);

        $pdf = Pdf::loadView('payroll.print', compact('period'))->setPaper('a4', 'landscape');

        return $pdf->stream('rekap-penggajian-' . $period->tahun . '-' . str_pad($period->bulan, 2, '0', STR_PAD_LEFT) . '.pdf');
    }

    public function slip(PayrollPeriod $period, PayrollItem $item)
    {
        $this->authorizePayrollAccess();

        abort_if((int) $item->payroll_period_id !== (int) $period->id, 404);

        $item->load(['teacher.user', 'details.leaveRequest.teacher', 'details.leaveRequest.infalTeacher']);

        $pdf = Pdf::loadView('payroll.slip', compact('period', 'item'))->setPaper('a4', 'portrait');

        return $pdf->stream('slip-gaji-' . str_replace(' ', '-', strtolower($item->teacher->nama_lengkap ?? 'guru')) . '.pdf');
    }

    private function generatePayroll(int $year, int $month): PayrollPeriod
    {
        $start = Carbon::create($year, $month, 1)->startOfDay();
        $end = $start->copy()->endOfMonth()->startOfDay();

        $period = PayrollPeriod::updateOrCreate(
            ['tahun' => $year, 'bulan' => $month],
            [
                'tanggal_mulai' => $start->toDateString(),
                'tanggal_selesai' => $end->toDateString(),
                'status' => 'draft',
                'generated_by' => auth()->id(),
            ]
        );

        $period->items()->each(function (PayrollItem $item) {
            $item->details()->delete();
        });
        $period->items()->delete();

        $payrollTeacherRoles = $this->payrollTeacherRoles;
        $deductedLeaveTypes = $this->deductedLeaveTypes;

        $teachers = Teacher::with(['user', 'salary', 'schedules'])
            ->whereHas('user', function ($query) use ($payrollTeacherRoles) {
                $query->where('status', 'aktif')
                    ->whereIn('role', $payrollTeacherRoles);
            })
            ->orderBy('nama_lengkap')
            ->get();

        $items = [];

        foreach ($teachers as $teacher) {
            $salary = $teacher->salary ?: $this->ensureTeacherSalary($teacher);

            $items[$teacher->id] = PayrollItem::create([
                'payroll_period_id' => $period->id,
                'teacher_id' => $teacher->id,
                'gaji_pokok' => (float) $salary->gaji_pokok,
                'potongan_absen' => 0,
                'tambahan_infal' => 0,
                'gaji_bersih' => (float) $salary->gaji_pokok,
                'jumlah_absen_diganti' => 0,
                'jumlah_mengganti' => 0,
            ]);
        }

        $leaves = LeaveRequest::with(['teacher.salary', 'teacher.schedules', 'infalTeacher.salary'])
            ->where('status_pengajuan', 'disetujui')
            ->where('status_infal', 'disetujui')
            ->whereNotNull('infal_teacher_id')
            ->whereIn('jenis_pengajuan', $deductedLeaveTypes)
            ->whereDate('tanggal_mulai', '<=', $end->toDateString())
            ->whereDate('tanggal_selesai', '>=', $start->toDateString())
            ->orderBy('tanggal_mulai')
            ->get();

        foreach ($leaves as $leave) {
            if (!$leave->teacher || !$leave->infalTeacher) {
                continue;
            }

            if (!isset($items[$leave->teacher_id])) {
                continue;
            }

            $teacherSalary = $leave->teacher->salary ?: $this->ensureTeacherSalary($leave->teacher);
            $potonganPerHari = $this->getPotonganPerAbsen((float) $teacherSalary->gaji_pokok);

            if ((float) $teacherSalary->potongan_per_absen !== $potonganPerHari) {
                $teacherSalary->update([
                    'potongan_per_absen' => $potonganPerHari,
                ]);
            }

            $dates = $this->payrollDatesForLeave($leave, $start, $end);

            if ($dates->isEmpty()) {
                continue;
            }

            $deductionItem = $items[$leave->teacher_id];
            $bonusItem = $items[$leave->infal_teacher_id] ?? null;

            foreach ($dates as $date) {
                $labelTanggal = $date->format('d/m/Y');

                $deductionItem->increment('potongan_absen', $potonganPerHari);
                $deductionItem->increment('jumlah_absen_diganti', 1);
                PayrollItemDetail::create([
                    'payroll_item_id' => $deductionItem->id,
                    'leave_request_id' => $leave->id,
                    'tanggal_event' => $date->toDateString(),
                    'tipe' => 'potongan_absen',
                    'nominal' => $potonganPerHari,
                    'keterangan' => 'Potongan ' . ucfirst(str_replace('_', ' ', $leave->jenis_pengajuan)) . ' pada ' . $labelTanggal . ', digantikan oleh ' . ($leave->infalTeacher->nama_lengkap ?? '-'),
                ]);

                if ($bonusItem) {
                    $bonusItem->increment('tambahan_infal', $potonganPerHari);
                    $bonusItem->increment('jumlah_mengganti', 1);
                    PayrollItemDetail::create([
                        'payroll_item_id' => $bonusItem->id,
                        'leave_request_id' => $leave->id,
                        'tanggal_event' => $date->toDateString(),
                        'tipe' => 'tambahan_infal',
                        'nominal' => $potonganPerHari,
                        'keterangan' => 'Tambahan guru pengganti untuk ' . ($leave->teacher->nama_lengkap ?? '-') . ' pada ' . $labelTanggal,
                    ]);
                }
            }
        }

        PayrollItem::where('payroll_period_id', $period->id)
            ->get()
            ->each(function (PayrollItem $item) {
                $item->update([
                    'gaji_bersih' => (float) $item->gaji_pokok - (float) $item->potongan_absen + (float) $item->tambahan_infal,
                ]);
            });

        return $period->fresh(['items']);
    }

    private function payrollDatesForLeave(LeaveRequest $leave, Carbon $periodStart, Carbon $periodEnd)
    {
        $start = $leave->tanggal_mulai->copy()->max($periodStart);
        $end = $leave->tanggal_selesai->copy()->min($periodEnd);

        $activeScheduleDays = $leave->teacher->schedules
            ? $leave->teacher->schedules->where('status', 'aktif')->pluck('hari')->toArray()
            : [];

        return collect(CarbonPeriod::create($start, $end))
            ->filter(function (Carbon $date) use ($activeScheduleDays) {
                if (empty($activeScheduleDays)) {
                    return true;
                }

                return in_array($this->indonesianDayKey($date), $activeScheduleDays, true);
            })
            ->values();
    }

    private function indonesianDayKey(Carbon $date): string
    {
        return [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$date->dayOfWeekIso];
    }

    private function ensureTeacherSalary(Teacher $teacher): TeacherSalary
    {
        return TeacherSalary::firstOrCreate(
            ['teacher_id' => $teacher->id],
            [
                'gaji_pokok' => 0,
                'potongan_per_absen' => $this->getPotonganPerAbsen(0),
                'keterangan' => 'Otomatis berdasarkan gaji pokok.',
            ]
        );
    }

    private function authorizePayrollAccess(): void
    {
        if (!auth()->check() || !in_array(auth()->user()->role, $this->payrollRoles, true)) {
            abort(403, 'Anda tidak memiliki akses ke menu penggajian.');
        }
    }

    public function bulkUpdateSettings(Request $request)
    {
        $user = auth()->user();

        if (!in_array($user->role, ['bendahara', 'super_admin'])) {
            abort(403, 'Anda tidak memiliki akses ke pengaturan gaji.');
        }

        $request->validate([
            'salaries' => 'required|array',
            'salaries.*.gaji_pokok' => 'required|numeric|min:0',
            'salaries.*.keterangan' => 'nullable|string|max:500',
        ]);

        foreach ($request->salaries as $teacherId => $data) {
            $gajiPokok = (float) ($data['gaji_pokok'] ?? 0);

            $potongan = $this->getPotonganPerAbsen($gajiPokok);

            TeacherSalary::updateOrCreate(
                [
                    'teacher_id' => $teacherId,
                ],
                [
                    'gaji_pokok' => $gajiPokok,
                    'potongan_per_absen' => $potongan,
                    'keterangan' => $data['keterangan'] ?? null,
                ]
            );
        }

        return back()->with('success', 'Semua pengaturan gaji guru berhasil disimpan.');
    }

    private function getEffectiveLeaveDates(
        Teacher $teacher,
        Carbon $tanggalMulai,
        Carbon $tanggalSelesai
    ): array {
        $schedules = $teacher->schedules
            ? $teacher->schedules->where('status', 'aktif')
            : collect();

        $dayMap = [
            'monday' => 'senin',
            'tuesday' => 'selasa',
            'wednesday' => 'rabu',
            'thursday' => 'kamis',
            'friday' => 'jumat',
            'saturday' => 'sabtu',
            'sunday' => 'minggu',
        ];

        $dates = [];

        foreach (CarbonPeriod::create($tanggalMulai, $tanggalSelesai) as $date) {
            /*
            | Guru lama yang belum memiliki jadwal harian:
            | tetap dihitung agar data payroll lama tidak hilang.
            */
            if ($schedules->isEmpty()) {
                $dates[] = $date->copy();
                continue;
            }

            $hari = $dayMap[strtolower($date->englishDayOfWeek)] ?? null;

            if ($hari && $schedules->contains('hari', $hari)) {
                $dates[] = $date->copy();
            }
        }

        return $dates;
    }

    private function getPotonganPerAbsen(float $gajiPokok): float
    {
        return $gajiPokok >= 1000000
            ? 30000
            : 20000;
    }

    // private function resolvePerPage(Request $request, int $default = 7): int
    // {
    //     $allowed = [7, 14, 21, 28, 35, 70];
    //     $perPage = (int) $request->input('per_page', $default);

    //     return in_array($perPage, $allowed, true) ? $perPage : $default;
    // }
}

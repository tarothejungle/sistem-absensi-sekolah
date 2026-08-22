<?php

namespace App\Http\Controllers;

use App\Models\PayrollAuditLog;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\Teacher;
use App\Models\TeacherSalary;
use App\Models\User;
use App\Services\PayrollGenerationService;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    private array $monthNames = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function index(Request $request)
    {
        $this->authorizePayrollAccess();
        $selectedYear = (int) $request->input('tahun', now()->year);
        $selectedMonth = (int) $request->input('bulan', now()->month);
        $periods = PayrollPeriod::with(['generator', 'finalizer'])
            ->orderByDesc('tahun')
            ->orderByDesc('bulan')
            ->paginate(10);
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

    public function settings()
    {
        $this->authorizePayrollAccess();
        $teachers = Teacher::with(['user', 'salary'])
            ->whereHas('user', fn ($query) => $query->whereIn('role', User::ATTENDANCE_ROLES))
            ->orderBy('nama_lengkap')
            ->paginate(7)
            ->withQueryString();

        $teachers->getCollection()->each(function (Teacher $teacher): void {
            if (! $teacher->salary) {
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
            'potongan_per_absen' => 'required|numeric|min:0',
            'keterangan' => 'nullable|string|max:500',
        ]);

        TeacherSalary::updateOrCreate(
            ['teacher_id' => $teacher->id],
            [
                'gaji_pokok' => $data['gaji_pokok'],
                'potongan_per_absen' => $data['potongan_per_absen'],
                'keterangan' => $data['keterangan'] ?? null,
            ],
        );

        return back()->with('success', 'Pengaturan gaji '.($teacher->nama_lengkap ?? 'guru').' berhasil diperbarui.');
    }

    public function bulkUpdateSettings(Request $request)
    {
        $this->authorizePayrollAccess();
        $data = $request->validate([
            'salaries' => 'required|array',
            'salaries.*.gaji_pokok' => 'required|numeric|min:0',
            'salaries.*.potongan_per_absen' => 'required|numeric|min:0',
            'salaries.*.keterangan' => 'nullable|string|max:500',
        ]);
        $teacherIds = collect(array_keys($data['salaries']))->map(fn ($id) => filter_var($id, FILTER_VALIDATE_INT));

        if ($teacherIds->contains(false)
            || Teacher::whereIn('id', $teacherIds)->count() !== $teacherIds->unique()->count()) {
            abort(422, 'Data guru pada pengaturan gaji tidak valid.');
        }

        DB::transaction(function () use ($data): void {
            foreach ($data['salaries'] as $teacherId => $salary) {
                TeacherSalary::updateOrCreate(
                    ['teacher_id' => (int) $teacherId],
                    [
                        'gaji_pokok' => $salary['gaji_pokok'],
                        'potongan_per_absen' => $salary['potongan_per_absen'],
                        'keterangan' => $salary['keterangan'] ?? null,
                    ],
                );
            }
        });

        return back()->with('success', 'Semua pengaturan gaji guru berhasil disimpan.');
    }

    public function generate(Request $request, PayrollGenerationService $service)
    {
        $this->authorizePayrollAccess();
        $data = $request->validate([
            'tahun' => 'required|integer|min:2020|max:2100',
            'bulan' => 'required|integer|min:1|max:12',
        ]);

        try {
            $service->generate((int) $data['tahun'], (int) $data['bulan'], (int) auth()->id());
        } catch (DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('payroll.index')
            ->with('success', 'Penggajian berhasil dibuat ulang dalam status draft.');
    }

    public function finalize(PayrollPeriod $period)
    {
        $this->authorizePayrollAccess();
        $updated = DB::transaction(function () use ($period): bool {
            $locked = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($locked->status === 'final') {
                return false;
            }
            $locked->update([
                'status' => 'final',
                'finalized_by' => auth()->id(),
                'finalized_at' => now(),
            ]);
            PayrollAuditLog::create([
                'payroll_period_id' => $locked->id,
                'user_id' => auth()->id(),
                'action' => 'finalized',
            ]);

            return true;
        });

        return back()->with($updated ? 'success' : 'error', $updated
            ? 'Periode payroll berhasil difinalisasi dan dikunci.'
            : 'Periode payroll sudah berstatus final.');
    }

    public function reopen(Request $request, PayrollPeriod $period)
    {
        abort_unless(auth()->user()?->role === 'super_admin', 403);
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000']);
        $updated = DB::transaction(function () use ($period, $data): bool {
            $locked = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($locked->status !== 'final') {
                return false;
            }
            $locked->update(['status' => 'draft', 'finalized_by' => null, 'finalized_at' => null]);
            PayrollAuditLog::create([
                'payroll_period_id' => $locked->id,
                'user_id' => auth()->id(),
                'action' => 'reopened',
                'reason' => $data['reason'],
            ]);

            return true;
        });

        return back()->with($updated ? 'success' : 'error', $updated
            ? 'Periode payroll dibuka kembali menjadi draft.'
            : 'Hanya periode final yang dapat dibuka kembali.');
    }

    public function show(PayrollPeriod $period)
    {
        $this->authorizePayrollAccess();
        $period->load(['generator', 'finalizer', 'audits.actor']);
        $items = PayrollItem::with(['teacher.user', 'details.leaveRequest.teacher', 'details.leaveRequest.infalTeachers'])
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
        $period->load(['items.teacher.user', 'items.details.leaveRequest.teacher', 'items.details.leaveRequest.infalTeachers']);

        return Pdf::loadView('payroll.print', compact('period'))
            ->setPaper('a4', 'landscape')
            ->stream('rekap-penggajian-'.$period->tahun.'-'.str_pad($period->bulan, 2, '0', STR_PAD_LEFT).'.pdf');
    }

    public function slip(PayrollPeriod $period, PayrollItem $item)
    {
        $this->authorizePayrollAccess();
        abort_if((int) $item->payroll_period_id !== (int) $period->id, 404);
        $item->load(['teacher.user', 'details.leaveRequest.teacher', 'details.leaveRequest.infalTeachers']);

        return Pdf::loadView('payroll.slip', compact('period', 'item'))
            ->setPaper('a4', 'portrait')
            ->stream('slip-gaji-'.str_replace(' ', '-', strtolower($item->teacher->nama_lengkap ?? 'guru')).'.pdf');
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

    private function authorizePayrollAccess(): void
    {
        abort_unless(auth()->check() && in_array(auth()->user()->role, ['bendahara', 'super_admin'], true), 403,
            'Anda tidak memiliki akses ke menu penggajian.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Exports\AttendanceReportExport;
use App\Models\Attendance;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $query = $this->filteredQuery($filters);
        $statusCounts = (clone $query)
            ->selectRaw('status_kehadiran, COUNT(*) as aggregate')
            ->groupBy('status_kehadiran')
            ->pluck('aggregate', 'status_kehadiran');
        $attendances = $query
            ->orderBy('tanggal', 'asc')
            ->orderBy('check_in_time', 'asc')
            ->paginate(25)
            ->withQueryString();

        $chartLabels = [
            'Hadir',
            'Terlambat',
            'Hadir Tidak Lengkap',
            'Izin',
            'Sakit',
            'Cuti',
            'Alfa',
        ];

        $chartData = [
            (int) ($statusCounts['hadir'] ?? 0),
            (int) ($statusCounts['terlambat'] ?? 0),
            (int) ($statusCounts['hadir_tidak_lengkap'] ?? 0),
            (int) ($statusCounts['izin'] ?? 0),
            (int) ($statusCounts['sakit'] ?? 0),
            (int) ($statusCounts['cuti'] ?? 0),
            (int) ($statusCounts['alfa'] ?? 0),
        ];

        return view('report.index', compact(
            'attendances',
            'chartLabels',
            'chartData'
        ));
    }

    public function exportExcel(Request $request)
    {
        $filters = $this->validatedFilters($request);

        return Excel::download(
            new AttendanceReportExport($filters),
            'laporan-absensi.xlsx'
        );
    }

    public function exportPdf(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $streamUrl = route('reports.stream.pdf', $filters);
        $downloadUrl = route('reports.download.pdf', $filters);

        return view('report.preview_pdf', compact('streamUrl', 'downloadUrl'));
    }

    public function streamPdf(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $attendances = $this->filteredQuery($filters)
            ->orderBy('tanggal', 'asc')
            ->orderBy('check_in_time', 'asc')
            ->get();

        return Pdf::loadView('report.export_pdf', compact('attendances'))
            ->setPaper('a4', 'landscape')
            ->stream('laporan-absensi.pdf');
    }

    public function downloadPdf(Request $request)
    {
        $filters = $this->validatedFilters($request);
        $attendances = $this->filteredQuery($filters)
            ->orderBy('tanggal', 'asc')
            ->orderBy('check_in_time', 'asc')
            ->get();

        $pdf = Pdf::loadView('report.export_pdf', compact('attendances'))
            ->setPaper('a4', 'landscape');

        return $pdf->download('laporan-absensi.pdf');
    }

    private function filteredQuery(array $filters): Builder
    {
        $query = Attendance::with(['teacher.user', 'temporaryLeaveRequest']);

        if (! empty($filters['start_date'])) {
            $query->whereDate('tanggal', '>=', $filters['start_date']);
        }

        if (! empty($filters['end_date'])) {
            $query->whereDate('tanggal', '<=', $filters['end_date']);
        }

        if (! empty($filters['status'])) {
            $query->where('status_kehadiran', $filters['status']);
        }

        return $query;
    }

    private function validatedFilters(Request $request): array
    {
        $validator = validator($request->all(), [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:hadir,terlambat,hadir_tidak_lengkap,izin,sakit,cuti,tugas_luar,alfa',
        ]);
        $validator->after(function ($validator) use ($request): void {
            if (! $request->filled('start_date') || ! $request->filled('end_date') || $validator->errors()->isNotEmpty()) {
                return;
            }
            $start = Carbon::parse($request->input('start_date'));
            $end = Carbon::parse($request->input('end_date'));
            if ($start->diffInDays($end) > 366) {
                $validator->errors()->add('end_date', 'Rentang laporan maksimal 366 hari.');
            }
        });

        return $validator->validate();
    }
}
